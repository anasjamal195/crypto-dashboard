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

class LiveTradeLongFutureServiceEXP2
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
                $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'LONG')->whereIn('symbol', $openSymbolsAll)->where('isActive', 1)->get();
            } else {
                $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'LONG')->where('isActive', 1)->get();
            }
        } else {
            $openSymbols = DB::table('live_trades_future_results')->where('trade_status', 'open')->where('targetProfit', '<', 0.7)->pluck('symbol');
            $tradeHandler = [];
            $delay = 500;
            if (count($openSymbols) != 0) {
                $delay = 300;
                $openSymbolsAll = DB::table('live_trades_future_results')->where('trade_status', 'open')->pluck('symbol');
                $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'LONG')->whereIn('symbol', $openSymbolsAll)->where('isActive', 1)->get();
            } else {
                $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'LONG')->where('isActive', 1)->get();
            }
        }


        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;
                $buy_coin_price = $tradeInstance->buyPrice;



                Log::info('FutureTraderLongEXP2: Current Trade');
                Log::info('FutureTraderLongEXP2: Coin: ' . $symbol);
                Log::info('FutureTraderLongEXP2: Account: ' . $trade_acc);
                Log::info('FutureTraderLongEXP2: Invested: ' . $buy_coin_price . ' $');

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

                Log::info('FutureTraderLongEXP2: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {


                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $supportResistanceContition = $CurrentCandle['close']  >  $supportResistance[7]['resistance'] &&
                        $secondLastCandle['close']  <  $supportResistance[7]['resistance'];


                    // Will skip this iteration is below value is false
                    $proceedCondition = $CurrentCandle['close'] > $CurrentCandle['open'] // Candle Should be in Bullish
                        && $CurrentCandle['close'] <= $supportResistance[7]['resistance'] * (1 + 0.003); // Current Price should be Below +0.3% of resistance


                    $maCondition = false;
                    $maCandleDistance = 0;

                    // Find CROSSOVER in last N candles candles (MA7 from Below MA25)
                    for ($i = count($candleData) - 2; $i >= 1; $i--) {
                        $maCondition =  ($candleData[$i + 1]['ma7'] > $candleData[$i + 1]['ma25']  && $candleData[$i - 1]['ma7'] < $candleData[$i - 1]['ma25']);
                        if ($maCondition) {
                            $maCandleDistance = (count($candleData) - 1) - $i;
                            break;
                        }
                    }

                    $maCondition =  $maCondition && $maCandleDistance <= 7;

                    if ($maCondition) {
                        for ($i = count($candleData) - 2; $i >= (count($candleData) - 2) - $maCandleDistance; $i--) {
                            if ($candleData[$i]['close'] < $candleData[$i]['open'] && $candleData[$i - 1]['close'] < $candleData[$i - 1]['open']) {
                                $maCondition = false;
                                break;
                            }
                        }
                    }


                    Log::info('FutureTraderShortEXP1: Resistance: ' . $supportResistanceContition);
                    Log::info('FutureTraderLongEXP2: MA Condition: ' . $maCondition);
                    Log::info('FutureTraderLongEXP2: MA MACandleDistance: ' . $maCandleDistance);

                    if ($supportResistanceContition && $maCondition && $proceedCondition) {

                        $lastOrderClose = DB::table('live_trades_future_results')->where('position','LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if($lastOrderClose){
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if($timeDiff < 20){
                                Log::info('FutureTraderLongEXP2: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('FutureTraderShortEXP1: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Support Resistance Fake Break and Order reversal (SHORT)');
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
                            'subject' => 'Skipped LONG: Account ' . User::find($tradeInstance->tradeAccount)->name,
                            'created_at' => Carbon::now('Asia/Karachi'),
                        ];



                        MailerService::sendSkipEmail($data);
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('FutureTraderLongEXP2: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {

        Log::info('FutureTraderLongEXP2: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $stopLoss = $buy_order['stopLoss'];
        $stopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];

        if ($targetProfit <= 1) {
            // Scenerio 1: If Current profit is less than 1%
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
            Log::info('FutureTraderLongEXP2: Current profit ' . $currentProfit);

            if ($currentCandle['close'] < $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);

                if ($currentProfit < 0) {
                    Log::info('FutureTraderLongEXP2: Placing new order to recover from loss of previous order: ' . $tradeInstance->symbol);
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
            if ($currentCandle['close'] < $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
            } else if ($isCandleClosing) {
                $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
                Log::info('FutureTraderLongEXP2: Current profit ' . $currentProfit);

                if ($currentCandle['close'] > $buy_order['previousPrice'] && $currentProfit > $targetProfit) {

                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'],
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[7]['support'],
                        'currentResistance' => $supportResistance[7]['resistance'],
                        'currentProfit' => $currentProfit,
                        'targetProfit' => $targetProfit,
                    ]);
                }
            }
        }
    }








    public static function updateTradeHandler($interval, $market = 'SPOT', $user_id)
    {
        $meta_prefix = '';
        if ($market == 'SPOT')
            $meta_prefix = '_spot';
        else
            $meta_prefix = '_future';
        $limit = CommonHelpers::getMetaValue($user_id, 'live_trade_coin_count' . $meta_prefix, 10);
        $coins = array_map(function ($value) {
            return $value['symbol'];
        }, json_decode(json_encode(CommonHelpers::getPriorityQueue($interval, $market, $limit)), true));


        foreach ($coins as $coin) {
            // $data = BinanceApiService::getCandleStickData($coin, $interval, 1000, null, $market);
            // $idealBuying = IdealTradeService::getIdealBuyingCandles($data);
            // $averages = IdealTradeService::getAverages($idealBuying);

            // Dumping Trade Handler data

            if (CommonHelpers::getMetaValue($user_id, 'is_auto_update_enable' . $meta_prefix, true) !== 'on') {
                continue;
            }


            // Handle LONG Trades

            // Remove Coins that are not in priority queue
            $leftoverEntries = DB::table('trade_handler')->whereNotIn('symbol', $coins)->where('tradeAccount', $user_id)->where('position', 'LONG')->where('market', $market)->where('interval', $interval)->get();
            foreach ($leftoverEntries as $leftoverCoin) {
                $open_order = CommonHelpers::checkOpenOrder($leftoverCoin->symbol, 'LONG', $market, $user_id);
                if (!$open_order['is_open']) {
                    DB::table('trade_handler')->where('id', $leftoverCoin->id)->delete();
                }
            }

            // Insert new priority data
            $trade_handler = [
                'market' => $market,
                'symbol' => $coin,
                'interval' => $interval,
                'position' => 'LONG',
                'leverage' => 2,
                'buyPrice' => CommonHelpers::getMetaValue($user_id, 'buy_price' . $meta_prefix, 6),
                'tradeAccount' => $user_id,
                'targetProfit' => CommonHelpers::getMetaValue($user_id, 'target_profit' . $meta_prefix, 0.4),
                'rsiThreshold' => 0,
                'obvLimit' => 0,
                'stochLimit' => 0,
                'isActive' => 1
            ];
            $existing = DB::table('trade_handler')->where('tradeAccount', $user_id)->where('market', $market)->where('position', 'LONG')->where('interval', $interval)->where('symbol', $coin)->first();
            if ($existing) {
                DB::table('trade_handler')
                    ->where('id', $existing->id)
                    ->update($trade_handler);
            } else {
                // Insert new record
                DB::table('trade_handler')->insert($trade_handler);
            }


            // Handle SHORT Trades
            // Remove Coins that are not in priority queue
            $leftoverEntries = DB::table('trade_handler')->whereNotIn('symbol', $coins)->where('tradeAccount', $user_id)->where('position', 'SHORT')->where('market', $market)->where('interval', $interval)->get();
            foreach ($leftoverEntries as $leftoverCoin) {
                $open_order = CommonHelpers::checkOpenOrder($leftoverCoin->symbol, 'SHORT', $market, $user_id);
                if (!$open_order['is_open']) {
                    DB::table('trade_handler')->where('id', $leftoverCoin->id)->delete();
                }
            }

            // Insert new priority data
            $trade_handler = [
                'market' => $market,
                'symbol' => $coin,
                'interval' => $interval,
                'position' => 'SHORT',
                'leverage' => 2,
                'buyPrice' => CommonHelpers::getMetaValue($user_id, 'buy_price' . $meta_prefix, 6),
                'tradeAccount' => $user_id,
                'targetProfit' => CommonHelpers::getMetaValue($user_id, 'target_profit' . $meta_prefix, 0.4),
                'rsiThreshold' => 0,
                'obvLimit' => 0,
                'stochLimit' => 0,
                'isActive' => 1
            ];

            $existing = DB::table('trade_handler')->where('tradeAccount', $user_id)->where('position', 'SHORT')->where('market', $market)->where('interval', $interval)->where('symbol', $coin)->first();
            if ($existing) {
                DB::table('trade_handler')
                    ->where('id', $existing->id)
                    ->update($trade_handler);
            } else {
                // Insert new record
                DB::table('trade_handler')->insert($trade_handler);
            }
            CommonHelpers::delayMS(600);
        }
        // dd($coins);

        return $coins;
    }
}
