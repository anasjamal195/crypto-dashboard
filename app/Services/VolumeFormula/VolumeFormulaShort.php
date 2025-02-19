<?php

namespace App\Services\VolumeFormula;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VolumeFormulaShort
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        // Handling trade account, open orders etc...
        if ($account) {
            $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->where('targetProfit', '<', 0.7)->pluck('symbol');
            $tradeHandler = [];
            $delay = 500;
            if (count($openSymbols) != 0) {
                $delay = 300;
                $openSymbolsAll = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
                $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'SHORT')->whereIn('symbol', $openSymbolsAll)->where('isActive', 1)->get();
            } else {
                $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'SHORT')->where('isActive', 1)->get();
            }
        } else {
            $openSymbols = DB::table('live_trades_future_results')->where('trade_status', 'open')->where('targetProfit', '<', 0.7)->pluck('symbol');
            $tradeHandler = [];
            $delay = 500;
            if (count($openSymbols) != 0) {
                $delay = 300;
                $openSymbolsAll = DB::table('live_trades_future_results')->where('trade_status', 'open')->pluck('symbol');
                $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->whereIn('symbol', $openSymbolsAll)->where('isActive', 1)->get();
            } else {
                $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->where('isActive', 1)->get();
            }
        }
        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;
                $buy_coin_price = $tradeInstance->buyPrice;



                Log::info('VolumeFormulaShort: Current Trade');
                Log::info('VolumeFormulaShort: Coin: ' . $symbol);
                Log::info('VolumeFormulaShort: Account: ' . $trade_acc);
                Log::info('VolumeFormulaShort: Invested: ' . $buy_coin_price . ' $');

                CommonHelpers::checkLosses($symbol, 'SHORT', $tradeInstance->tradeAccount, 'Volume Formula');


                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                Log::info('VolumeFormulaShort: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {



                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];

                    $supportResistanceBand = $currentCandle['close'] >= ($supportResistance[7]['resistance'] * (1 - 0.3 / 100)) && $currentCandle['close'] <= ($supportResistance[7]['resistance'] * (1 + 0.3 / 100));
                    $candleTrend = $currentCandle['close'] < $currentCandle['open'] && $secondLastCandle['close'] > $secondLastCandle['open'];
                    $priceThreshold = abs($secondLastCandle['close'] - $secondLastCandle['open']);
                    $finalThresholdCondition = $currentCandle['close'] < ($secondLastCandle['high'] - ($priceThreshold * 0.4));
                    $stochCondition = $secondLastCandle['stoch_d'] >= 92;

                    if ($supportResistanceBand && $candleTrend && $finalThresholdCondition && $stochCondition) {
                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if ($timeDiff < 20) {
                                Log::info('VolumeFormulaShort: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('VolumeFormulaShort: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Volume Formula');
                    }
                }
                CommonHelpers::delayMS(200);
            } catch (\Exception $e) {
                Log::error('VolumeFormulaShort: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {
        Log::info('VolumeFormulaShort: Open order found for ' . $buy_order['symbol']);
        $market = $tradeInstance->market;
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $previousCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];
        $stopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];

        if ($targetProfit <= 1) {
            // Scenerio 1: If Current profit is less than 1%
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
            Log::info('VolumeFormulaShort: Current profit ' . $currentProfit);

            if ($currentCandle['close'] > $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
                if ($currentProfit < 0) {
                    Log::info('VolumeFormulaShort: Placing new order to recover from loss of previous order: ' . $tradeInstance->symbol);
                    BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'SELL' : 'BUY', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Volume Formula');
                }
            } else if ($currentProfit > $targetProfit) {

                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                    'stopLoss' =>  $currentCandle['close'],
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentSupport' => $supportResistance[7]['support'],
                    'currentResistance' => $supportResistance[7]['resistance'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit + 0.3,
                ]);
            } else {
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
            }
        } else {
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;

            if ($currentCandle['close'] < $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
            } else if ($isCandleClosing) {
                Log::info('VolumeFormulaShort: Current profit ' . $currentProfit);

                if ($currentProfit > $targetProfit) {

                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'],
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[7]['support'],
                        'currentResistance' => $supportResistance[7]['resistance'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit + 0.3,
                    ]);
                }
            }
        }
    }
}
