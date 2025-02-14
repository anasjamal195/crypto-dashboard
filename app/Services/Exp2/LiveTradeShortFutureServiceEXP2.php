<?php

namespace App\Services\Exp2;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeShortFutureServiceEXP2
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



                Log::info('FutureTraderShortEXP2: Current Trade');
                Log::info('FutureTraderShortEXP2: Coin: ' . $symbol);
                Log::info('FutureTraderShortEXP2: Account: ' . $trade_acc);
                Log::info('FutureTraderShortEXP2: Invested: ' . $buy_coin_price . ' $');


                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                Log::info('FutureTraderShortEXP2: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {



                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $supportResistanceContition = $CurrentCandle['close']  <  $supportResistance[7]['support'] &&
                        $secondLastCandle['close']  >  $supportResistance[7]['support'];



                    // Will skip this iteration is below value is false
                    $proceedCondition = $CurrentCandle['close'] < $CurrentCandle['open'] // Candle Should be in Bearish
                        && $CurrentCandle['close'] >= $supportResistance[7]['support'] * (1 - 0.003); // Current Price should be above -0.3% of support





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

                    Log::info('FutureTraderShortEXP2: Support: ' . $supportResistanceContition);
                    Log::info('FutureTraderShortEXP2: MA Condition: ' . $maCondition);
                    Log::info('FutureTraderShortEXP2: MA MACandleDistance: ' . $maCandleDistance);



                    if ($supportResistanceContition && $maCondition && $proceedCondition) {
                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if ($timeDiff < 20 && $lastOrderClose->currentProfit < 0) {
                                Log::info('FutureTraderShortEXP2: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('FutureTraderShortEXP2: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'MA7/MA25 Crossover with Support Resistance Break (SHORT)');
                    }

                    if ($supportResistanceContition && $maCondition && !$proceedCondition) {
                        $data =  [
                            'orderId' => '',
                            'symbol' => $symbol,
                            'side' =>  $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL',
                            'amount' => '',
                            'type' => '',
                            'position' => $tradeInstance->position,
                            'qty' => '',
                            'leverage' => '',
                            'stopLoss' => '',
                            'stopLossReductionPrecentage' => 0.1,
                            'price' => $CurrentCandle['close'],
                            'trade_status' => 'open',
                            'trade_acc' => $tradeInstance->tradeAccount,
                            'targetProfit' => 0.5,
                            'formula' => '',
                            'liqPrice' => '',
                            'subject' => 'Skipped SHORT: Account ' . User::find($tradeInstance->tradeAccount)->name,
                            'created_at' => Carbon::now('Asia/Karachi'),
                        ];



                        MailerService::sendSkipEmail($data);
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('FutureTraderShortEXP2: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {
        Log::info('FutureTraderShortEXP2: Open order found for ' . $buy_order['symbol']);
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
            Log::info('FutureTraderShortEXP2: Current profit ' . $currentProfit);

            if ($currentCandle['close'] > $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
                if ($currentProfit < 0) {
                    Log::info('FutureTraderShortEXP2: Placing new order to recover from loss of previous order: ' . $tradeInstance->symbol);
                    BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'SELL' : 'BUY', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Support Resistance Fake Break and Order reversal (SHORT)');
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
                Log::info('FutureTraderShortEXP2: Current profit ' . $currentProfit);

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
