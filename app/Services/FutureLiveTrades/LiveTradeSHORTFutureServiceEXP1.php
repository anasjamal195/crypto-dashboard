<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For SHORT Trades in future market
Will Target SHORT Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\FutureLiveTrades;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeSHORTFutureServiceEXP1
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_status', 'open')->where('targetProfit', '<', 1)->pluck('symbol');
        $tradeHandler = [];
        $delay = 500;
        if (count($openSymbols) != 0) {
            $delay = 300;
            $openSymbolsAll = DB::table('live_trades_future_results')->where('trade_status', 'open')->pluck('symbol');
            $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->whereIn('symbol', $openSymbolsAll)->where('isActive', 1)->get();
        } else {
            $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->where('isActive', 1)->get();
        }
        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;
                $buy_coin_price = $tradeInstance->buyPrice;



                Log::info('FutureTraderShortEXP1: Current Trade');
                Log::info('FutureTraderShortEXP1: Coin: ' . $symbol);
                Log::info('FutureTraderShortEXP1: Account: ' . $trade_acc);
                Log::info('FutureTraderShortEXP1: Invested: ' . $buy_coin_price . ' $');


                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [5]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                Log::info('FutureTraderShortEXP1: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {



                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $supportResistanceContition = $CurrentCandle['close']  <  $supportResistance[5]['support'] &&
                        $secondLastCandle['close']  >  $supportResistance[5]['support'];
                    // && $CurrentCandle['close'] >= $supportResistance[5]['support'] * (1 - 0.002);

                    $maCondition = false;
                    $maCandleDistance = 0;

                    // Find CROSSOVER in last N candles candles (MA7 from Below MA25)
                    for ($i = count($candleData) - 2; $i >= 1; $i--) {
                        $maCondition =  ($candleData[$i + 1]['ma7'] < $candleData[$i + 1]['ma25']  && $candleData[$i - 1]['ma7'] > $candleData[$i - 1]['ma25']);
                        if ($maCondition) {
                            $maCandleDistance = (count($candleData) - 1) - $i;
                            break;
                        }
                    }

                    $maCondition =  $maCondition && $maCandleDistance <= 10;
                    if ($maCondition) {
                        for ($i = count($candleData) - 2; $i >= (count($candleData) - 2) - $maCandleDistance; $i--) {
                            if ($candleData[$i]['close'] > $candleData[$i]['open'] && $candleData[$i - 1]['close'] > $candleData[$i - 1]['open']) {
                                $maCondition = false;
                                break;
                            }
                        }
                    }

                    Log::info('FutureTraderShortEXP1: Support: ' . $supportResistanceContition);
                    Log::info('FutureTraderShortEXP1: MA Condition: ' . $maCondition);
                    Log::info('FutureTraderShortEXP1: MA MACandleDistance: ' . $maCandleDistance);

                    if ($supportResistanceContition && $maCondition ) {
                        Log::info('FutureTraderShortEXP1: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'MA7/MA25 Crossover with Support Resistance Break (SHORT)');
                    }
                }
                CommonHelpers::delayMS($delay);
            } catch (\Exception $e) {
                Log::error('FutureTraderShortEXP1: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {
        Log::info('FutureTraderShortEXP1: Open order found for ' . $buy_order['symbol']);
        $market = $tradeInstance->market;
        $targetProfit = $tradeInstance->targetProfit;
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $previousCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];
        $stopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];

        if ($targetProfit <= 1) {
            // Scenerio 1: If Current profit is less than 1%
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
            Log::info('FutureTraderShortEXP1: Current profit ' . $currentProfit);

            if ($currentCandle['close'] > $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
            } else if ($currentCandle['close'] < $buy_order['previousPrice'] && $currentProfit > $targetProfit) {
                $targetProfit += 0.5;
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                    'stopLoss' =>  $currentCandle['close'] * (1 + 0.003),
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentSupport' => $supportResistance[5]['support'],
                    'currentResistance' => $supportResistance[5]['resistance'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
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
                Log::info('FutureTraderShortEXP1: Current profit ' . $currentProfit);

                if ($currentCandle['close'] < $buy_order['previousPrice'] && $currentProfit > $targetProfit) {
                    $targetProfit += 0.5;
                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'] * (1 + 0.003),
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[5]['support'],
                        'currentResistance' => $supportResistance[5]['resistance'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit,
                    ]);
                }
            }
        }
    }
}
