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

class RsiBreakoutFormulaLong
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



                Log::info('RsiBreakoutFormulaLong: Current Trade');
                Log::info('RsiBreakoutFormulaLong: Coin: ' . $symbol);
                Log::info('RsiBreakoutFormulaLong: Account: ' . $trade_acc);
                Log::info('RsiBreakoutFormulaLong: Invested: ' . $buy_coin_price . ' $');

                $data =  BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');
                $supportResistanceData = array_slice($data, count($data) - 1 - 300, 300);

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceData, [6]);
                $candleData = $data;
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;



                Log::info('RsiBreakoutFormulaLong: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');
                CommonHelpers::checkLosses($symbol, 'LONG', $tradeInstance->tradeAccount, 'RSIBreakout Formula');

                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $candleData, $isCandleClosing);
                } else {


                    $candle = $data[count($data) - 2];
                    $secondCandle = $data[count($data) - 2];
                    $index =  count($data) - 1;

                    $obvCandles = 15;
                    $idealBuying = IdealTradeService::getIdealOpeningCandlesLong($data);
                    $averages = IdealTradeService::getAverages($idealBuying);
                    $rsiThreshold = $averages['rsi6'];
                    $stochDLimit = $averages['stoch_d'] * 2;
                    $obvLimit = $averages['previousObvHigh'] ? (($averages['previousObvHigh'] - $averages['obv']) / $averages['previousObvHigh']) * 100 : 100;

                    // dd($symbol,$index,$idealBuying);
                    if (empty($idealBuying))
                        continue;
                    $averages = IdealTradeService::getAverages($idealBuying);

                    $basicRsiCondition = $candle['rsi6'] < $rsiThreshold && ($candle['ma7'] < $candle['ma25'] && $candle['ma25'] < $candle['ma99']);
                    $previousHighObv = $candle['obv'];
                    for ($i = $index - $obvCandles; $i <= $index; $i++) {
                        if ($data[$i]['obv'] > $previousHighObv) {
                            $previousHighObv = $data[$i]['obv'];
                        }
                    }

                    $stochCondition =   ($candle['stoch_d'] <=  $stochDLimit);
                    $obvCondition = ($candle['obv'] <= ($previousHighObv * (1 - $obvLimit / 100)));
                    $wrCondition  = true;
                    $obvPositiveCondition = true;
                    $difCondition = true;
                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($data, [6]);
                    $supportResistanceCondition = $candle['close'] < $supportResistance[6]['resistance'] && $candle['close'] > $supportResistance[6]['support'];


                    if (!($basicRsiCondition && $obvCondition && $stochCondition && $wrCondition && $obvPositiveCondition && $difCondition && $supportResistanceCondition)) {
                        // if (!$isCandleClosing) {
                        //     Log::info("RsiBreakoutFormulaLong: Condition false: isCandleClosing. Time gap: " . (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . " seconds");
                        // }
                        if (!$basicRsiCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: basicRsiCondition. rsi6: " . $candle['rsi6'] . ", threshold: " . $rsiThreshold . ", MA7: " . $candle['ma7'] . ", MA25: " . $candle['ma25'] . ", MA99: " . $candle['ma99']);
                        }
                        if (!$obvCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: obvCondition. OBV: " . $candle['obv'] . ", Highest OBV in last {$obvCandles} candles: " . $previousHighObv . ", OBV limit: " . $obvLimit);
                        }
                        if (!$stochCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: stochCondition. stoch_d: " . $candle['stoch_d'] . ", limit: " . $stochDLimit);
                        }
                        if (!$wrCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: wrCondition evaluated to false.");
                        }
                        if (!$obvPositiveCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: obvPositiveCondition evaluated to false.");
                        }
                        if (!$difCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: difCondition evaluated to false.");
                        }
                        if (!$supportResistanceCondition) {
                            Log::info("RsiBreakoutFormulaLong: Condition false: supportResistanceCondition. close: " . $candle['close'] . ", resistance: " . $supportResistance[6]['resistance'] . ", support: " . $supportResistance[6]['support']);
                        }
                    }
                    if ($basicRsiCondition && $obvCondition && $stochCondition && $wrCondition && $obvPositiveCondition && $difCondition && $supportResistanceCondition) {
                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
                            if ($timeDiff < 20) {
                                Log::info('RsiBreakoutFormulaLong: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('RsiBreakoutFormulaLong: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'RSIBreakout Formula', true);
                    }
                }

                CommonHelpers::delayMS(200);
            } catch (\Exception $e) {
                Log::error('RsiBreakoutFormulaLong: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $candleData, $isCandleClosing): void
    {

        Log::info('RsiBreakoutFormulaLong: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];
        $stopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];

        if ($targetProfit <= 1) {
            // Scenerio 1: If Current profit is less than 1%
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
            Log::info('RsiBreakoutFormulaLong: Current profit ' . $currentProfit);

            if ($secondCandle['close'] < $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
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
            if ($secondCandle['close'] < $stopLoss) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
            } else if ($isCandleClosing) {
                $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
                Log::info('RsiBreakoutFormulaLong: Current profit ' . $currentProfit);

                if ($currentCandle['close'] > $buy_order['previousPrice'] && $currentProfit > $targetProfit) {

                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'],
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[6]['support'],
                        'currentResistance' => $supportResistance[6]['resistance'],
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
