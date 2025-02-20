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

class VolumeFormulaLong
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



                Log::info('VolumeFormulaLong: Current Trade');
                Log::info('VolumeFormulaLong: Coin: ' . $symbol);
                Log::info('VolumeFormulaLong: Account: ' . $trade_acc);
                Log::info('VolumeFormulaLong: Invested: ' . $buy_coin_price . ' $');

                $data =  BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');
                $supportResistanceData = array_slice($data, count($data) - 1 - 300, 300);

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceData, [15]);
                $candleData = $data;
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

                Log::info('VolumeFormulaLong: Closing time gap: ' .  (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) . ' seconds');
                CommonHelpers::checkLosses($symbol, 'LONG', $tradeInstance->tradeAccount, 'Volume Formula');

                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $supportResistance, $isCandleClosing);
                } else {


                    $candle = $data[count($data) - 1];
                    $secondCandle = $data[count($data) - 2];
                    $breakoutCondition = $secondCandle['close'] > $supportResistance[15]['resistance'] && $secondCandle['open'] < $supportResistance[15]['resistance'];

                    $averageTrailingVolume = 0;
                    $volumeCandlesCount = 0;
                    $indexCounter = count($data) - 1;
                    $loopVolume = true;
                    while ($loopVolume) {
                        if ($data[$indexCounter]['close'] > $data[$indexCounter]['open']) {
                            $averageTrailingVolume += $data[$indexCounter]['volume'];
                            $volumeCandlesCount++;
                            $indexCounter--;
                        } else {
                            $loopVolume = false;
                        }
                    }

                    $averageTrailingVolume = $averageTrailingVolume && $volumeCandlesCount ? $averageTrailingVolume / $volumeCandlesCount :  0;
                    $volumeMultiplier = 2;
                    $volumeCondition = $candle['volume'] > $averageTrailingVolume * $volumeMultiplier && $averageTrailingVolume != 0;

                    $supportResistanceDataSecond = array_slice($data, count($data) - 1  - 300 - $supportResistance[15]['resistanceDistance'], 300);
                    $secondSupportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceDataSecond, [15]);

                    if ($breakoutCondition && $volumeCondition && $secondCandle['close'] > $secondSupportResistance[15]['resistance']) {

                        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                        if ($lastOrderClose) {
                            $lastOrderClose = $lastOrderClose->created_at;
                            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
                            if ($timeDiff < 20) {
                                Log::info('VolumeFormulaLong: Skipped due to last order close time: ' . $symbol);
                                continue;
                            }
                        }
                        Log::info('VolumeFormulaLong: Conditions Staisfied, opening now : ' . $symbol);

                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, 'Volume Formula');
                    }
                }

                CommonHelpers::delayMS(200);
            } catch (\Exception $e) {
                Log::error('VolumeFormulaLong: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $isCandleClosing): void
    {

        Log::info('VolumeFormulaLong: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $stopLoss = $buy_order['stopLoss'];
        $stopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];

        if ($targetProfit <= 1) {
            // Scenerio 1: If Current profit is less than 1%
            $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
            Log::info('VolumeFormulaLong: Current profit ' . $currentProfit);

            if ($currentCandle['close'] < $stopLoss) {
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
                    'currentSupport' => $supportResistance[15]['support'],
                    'currentResistance' => $supportResistance[15]['resistance'],
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
                Log::info('VolumeFormulaLong: Current profit ' . $currentProfit);

                if ($currentCandle['close'] > $buy_order['previousPrice'] && $currentProfit > $targetProfit) {

                    DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                        'stopLossReductionPrecentage' => $stopLossReductionPrecentage,
                        'stopLoss' =>  $currentCandle['close'],
                        'previousPrice' => $currentCandle['close'],
                        'currentPrice' => $currentCandle['close'],
                        'currentSupport' => $supportResistance[15]['support'],
                        'currentResistance' => $supportResistance[15]['resistance'],
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
