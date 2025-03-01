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
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeSHORTFutureServiceEXP1
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        // Handling trade account, open orders etc...
        if ($account) {
            // $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->where('targetProfit', '<', 0.3)->pluck('symbol');
            $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
            if (count($openSymbols) <= 3) {
                $openSymbols = [];
            }
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


                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                Log::info('FutureTraderShortEXP1: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {



                    // Limit Open order count to 3


                    if (DB::table('live_trades_future_results')->where('trade_status', 'open')->count() >= 1) {
                        continue;
                    }



                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $supportResistanceContition = $CurrentCandle['close']  <  $supportResistance[7]['support'] &&
                        $secondLastCandle['close']  >  $supportResistance[7]['support'];



                    // Will skip this iteration is below value is false
                    $proceedCondition = $CurrentCandle['close'] < $CurrentCandle['open'] // Candle Should be in Bearish
                        && $CurrentCandle['close'] >= $supportResistance[7]['support'] * (1 - 0.0035); // Current Price should be above -0.3% of support





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

                    $averageTrailingVolume = 0;
                    $volumeCandlesCount = 0;
                    $indexCounter = count($candleData) - 1;
                    $loopVolume = true;
                    while ($loopVolume) {
                        if ($candleData[$indexCounter]['close'] < $candleData[$indexCounter]['open']) {
                            $averageTrailingVolume += $candleData[$indexCounter]['volume'];
                            $volumeCandlesCount++;
                            $indexCounter--;
                        } else {
                            $loopVolume = false;
                        }
                    }

                    $averageTrailingVolume = $averageTrailingVolume && $volumeCandlesCount ? $averageTrailingVolume / $volumeCandlesCount :  0;
                    $volumeMultiplier = 1.3;
                    $volumeCondition = $CurrentCandle['volume'] > $averageTrailingVolume * $volumeMultiplier && $averageTrailingVolume != 0;


                    Log::info('FutureTraderShortEXP1: Support: ' . $supportResistanceContition);
                    Log::info('FutureTraderShortEXP1: MA Condition: ' . $maCondition);
                    Log::info('FutureTraderShortEXP1: MA MACandleDistance: ' . $maCandleDistance);



                    if ($supportResistanceContition && $maCondition && $proceedCondition && $volumeCondition) {
                        Log::info('FutureTraderShortEXP1: Conditions Staisfied, opening now : ' . $symbol);
                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if ($timeDiff < 20) {
                                Log::info('FutureTraderShortEXP1: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        $lower_wick = CommonHelpers::isCandleWick($CurrentCandle, 'lower', 5, $supportResistance[7]['support'], $symbol);
                        if (!$lower_wick) {
                            BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Support/Resistance Breakout');
                        } else {
                            Log::info('FutureTraderShortEXP1: Retreating Due to lower wick');
                            MailerService::sendSkipEmail($tradeInstance, 'Skipped opening SHORT Due to Wick formation ' . $symbol);
                        }
                    }
                }
                CommonHelpers::delayMS(100);
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
        $targetProfit = $buy_order['targetProfit'];
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
                $upper_wick = CommonHelpers::isCandleWick($currentCandle, 'upper', 5, $stopLoss, $tradeInstance->symbol);
                if (!$upper_wick) {
                    BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit,
                    ]);
                } else {
                    Log::info('FutureTraderShortEXP1: Retreating Due to lower wick');
                    MailerService::sendSkipEmail($tradeInstance, 'Skipped closing SHORT Due to Wick formation ' . $tradeInstance->symbol);
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
                $upper_wick = CommonHelpers::isCandleWick($currentCandle, 'upper', 5, $stopLoss, $tradeInstance->symbol);
                if (!$upper_wick) {
                    BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit,
                    ]);
                } else {
                    Log::info('FutureTraderShortEXP1: Retreating Due to lower wick');
                    MailerService::sendSkipEmail($tradeInstance, 'Skipped closing SHORT Due to Wick formation ' . $tradeInstance->symbol);
                }
            } else if ($isCandleClosing) {
                Log::info('FutureTraderShortEXP1: Current profit ' . $currentProfit);

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
