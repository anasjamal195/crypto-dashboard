<?php

namespace App\Services\RsiBreakoutFormula;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RsiBreakoutFormulaShort
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



                Log::info('RsiBreakoutFormulaShort: Current Trade');
                Log::info('RsiBreakoutFormulaShort: Coin: ' . $symbol);
                Log::info('RsiBreakoutFormulaShort: Account: ' . $trade_acc);
                Log::info('RsiBreakoutFormulaShort: Invested: ' . $buy_coin_price . ' $');

                CommonHelpers::checkLosses($symbol, 'SHORT', $tradeInstance->tradeAccount, 'Volume Formula');


                $data =  BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');
                $supportResistanceData = array_slice($data, count($data) - 1 - 300, 300);

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceData, [6]);
                $candleData = $data;
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

                Log::info('RsiBreakoutFormulaShort: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {



                    $candle = $data[count($data) - 1];
                    $secondCandle = $data[count($data) - 2];
                    $index =  count($data) - 1;


                    $obvCandles = 15;
                    $idealBuying = IdealTradeService::getIdealOpeningCandlesShort(array_slice($data, $index - 1000, 1000));
                    $averages = IdealTradeService::getAverages($idealBuying);

                    $rsiThreshold = $averages['rsi6'];
                    $stochDLimit = 100 - $averages['stoch_rsi'] * 2;
                    $obvLimit = $averages['previousObvLow'] ? (($averages['previousObvLow'] - $averages['obv']) / $averages['previousObvLow']) * 100 : 0;


                    // dd($symbol,$index,$idealBuying);
                    if (empty($idealBuying))
                        continue;
                    $averages = IdealTradeService::getAverages($idealBuying);


                    $previousLowObv = $candle['obv'];
                    for ($i = $index - $obvCandles; $i <= $index; $i++) {
                        if ($data[$i]['obv'] < $previousLowObv) {
                            $previousLowObv = $data[$i]['obv'];
                        }
                    }

                    $basicRsiCondition = $candle['rsi6'] > $rsiThreshold && ($candle['ma7'] > $candle['ma25'] && $candle['ma25'] > $candle['ma99']);
                    $stochCondition =   ($candle['stoch_d'] >=  $stochDLimit);
                    $obvCondition = ($candle['obv'] >= ($previousLowObv * (1 + $obvLimit / 100)));

                    $wrCondition  = true;

                    $obvPositiveCondition = true;
                    $difCondition = true;

                    $supportResistanceData = array_slice($data, $index - 300, 300);
                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceData, [6]);

                    $supportResistanceCondition = $candle['close'] < $supportResistance[6]['resistance'] && $candle['close'] > $supportResistance[6]['support'];


                    if ($isCandleClosing && $basicRsiCondition && $obvCondition && $stochCondition && $wrCondition && $obvPositiveCondition && $difCondition && $supportResistanceCondition) {

                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if ($timeDiff < 20) {
                                Log::info('RsiBreakoutFormulaShort: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('RsiBreakoutFormulaShort: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Volume Formula');
                    }
                }
                CommonHelpers::delayMS(200);
            } catch (\Exception $e) {
                Log::error('RsiBreakoutFormulaShort: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {
        Log::info('RsiBreakoutFormulaShort: Open order found for ' . $buy_order['symbol']);
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
            Log::info('RsiBreakoutFormulaShort: Current profit ' . $currentProfit);

            if ($currentCandle['close'] > $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
                if ($currentProfit < 0) {
                    Log::info('RsiBreakoutFormulaShort: Placing new order to recover from loss of previous order: ' . $tradeInstance->symbol);
                    BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'SELL' : 'BUY', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Volume Formula');
                }
            } else if ($currentProfit > $targetProfit) {

                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                    'stopLoss' =>  $currentCandle['close'],
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentSupport' => $supportResistance[6]['support'],
                    'currentResistance' => $supportResistance[6]['resistance'],
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
                Log::info('RsiBreakoutFormulaShort: Current profit ' . $currentProfit);

                if ($currentProfit > $targetProfit) {

                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'],
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[6]['support'],
                        'currentResistance' => $supportResistance[6]['resistance'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit + 0.3,
                    ]);
                }
            }
        }
    }
}
