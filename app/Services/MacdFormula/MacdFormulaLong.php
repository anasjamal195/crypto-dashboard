<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For Long Trades in future market
Will Target Long Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\MacdFormula;

use App\CommonHelpers;
use App\Jobs\Threads\LongThread;
use App\Jobs\ThreadsMACD\LongThread as ThreadsMACDLongThread;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MacdFormulaLong
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'LONG')->where('isWorkerDispatched', false)->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('LongWorkerMACD: Worker Started');

        foreach ($tradeHandler as  $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;

                $openWorkersCount = DB::table('trade_handler')->where('isWorkerDispatched', true)->where('position', 'LONG')->count();
                if ($openWorkersCount >= 9) {
                    continue;
                }

                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);

                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    continue;
                } else {

                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                    $candleData = $supportResistance['candleData'];
                    $index = count($candleData) - 1;
                    $data = $candleData;

                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];

                    // Use Last completed Candle for checking conditions
                    $index--;

                    $macdDarkRedDistance = 0;
                    $loopIndex = $index;

                    while (true) {

                        if ($data[$loopIndex]['histogram'] >= $data[$loopIndex - 1]['histogram']) {
                            $macdDarkRedDistance++;
                        } else {
                            break;
                        }

                        $loopIndex--;
                    }

                    $slopeRatio = ($data[$index]['dea'] - $data[$index - 1]['dea']) ? ($data[$index]['dif'] - $data[$index - 1]['dif']) / ($data[$index]['dea'] - $data[$index - 1]['dea']) : 0;

                    $isWorkerDispatched = DB::table('trade_handler')->where('id', $tradeInstance->id)->first()->isWorkerDispatched;


                    // New Formula 
                    $totalRedCandles = 0;
                    $loopIndex = $index;

                    while (true) {

                        if ($data[$loopIndex]['histogram'] > 0)
                            break;
                        $totalRedCandles++;

                        $loopIndex--;
                    }

                    $volumeCrossover = false;
                    $loopIndex = $index;

                    while (true) {

                        if ($data[$loopIndex]['volumeMA5'] > $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] < $data[$loopIndex - 1]['volumeMA10']) {
                            $volumeCrossover = true;
                            break;
                        }
                        if ($data[$loopIndex]['volumeMA5'] < $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] > $data[$loopIndex - 1]['volumeMA10']) {
                            break;
                        }
                        $loopIndex--;
                    }


                    $kdjCrossover = false;
                    $kdjthreshold = 0;
                    $loopIndex = $index;

                    while (true) {
                        if (
                            $data[$loopIndex]['J'] > $data[$loopIndex]['K'] * (1 + $kdjthreshold / 100) &&
                            $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['K'] * (1 + $kdjthreshold / 100)
                            &&
                            $data[$loopIndex]['J'] > $data[$loopIndex]['D'] * (1 + $kdjthreshold / 100) &&
                            $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['D'] * (1 + $kdjthreshold / 100)
                        ) {
                            $kdjCrossover = true;
                            break;
                        }

                        if (
                            ($data[$loopIndex]['J'] < $data[$loopIndex]['K'] * (1 + $kdjthreshold / 100) &&
                                $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['K'] * (1 + $kdjthreshold / 100)
                                &&
                                $data[$loopIndex]['J'] < $data[$loopIndex]['D'] * (1 + $kdjthreshold / 100) &&
                                $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['D'] * (1 + $kdjthreshold / 100))
                            ||
                            $loopIndex == 1
                        ) {
                            break;
                        }

                        $loopIndex--;
                    }

                    // Check KDJ approaching Crossover
                    $kdjApproachingCrossover = abs($data[$index]['K'] - $data[$index]['J']) < abs($data[$index - 1]['K'] - $data[$index - 1]['J']) &&
                        abs($data[$index]['D'] - $data[$index]['J']) < abs($data[$index - 1]['D'] - $data[$index - 1]['J']);



                    // Check downward wick
                    $upperWick = ($data[$index]['high'] - $data[$index]['close']);
                    $lowerWick = ($data[$index]['open'] - $data[$index]['low']);
                    $isDownwardWick = $data[$index]['close'] > $data[$index]['open'] && $lowerWick > $upperWick * 2;


                    $lastLowest = $data[$index]['low'];
                    $loopIndex = $index;
                    while (true) {
                        if ($data[$loopIndex]['low'] < $lastLowest) {
                            $lastLowest = $data[$loopIndex]['low'];
                        } else if ($data[$loopIndex]['low'] > $data[$index]['low'] || $loopIndex == 1) {
                            break;
                        }
                        $loopIndex--;
                    }

                    if (
                        // Check for two bullish and one berish candles
                        // $data[$index]['per'] > 0 && $data[$index - 1]['per'] > 0 && $data[$index - 2]['per'] < 0 &&

                        // ($data[$index]['histogram'] < 0 || $data[$index - 1]['histogram'] < 0) &&

                        // $data[$index]['dif'] > $data[$index - 1]['dif'] && $macdDarkRedDistance >= 6 &&


                        $data[$index]['histogram'] < 0
                        && $isDownwardWick
                        && ($kdjCrossover || $kdjApproachingCrossover)
                        && $totalRedCandles > 4
                        && $data[$index]['per'] >= 0.2
                        && $data[$index]['per'] < 0.6
                        && $data[$index]['close'] < $lastLowest * (1 + 0.7 / 100)
                        && $data[$index]['avl'] > $data[$index - 1]['avl']
                        && $data[$index]['dif'] > $data[$index - 1]['dif']
                        && $data[$index]['rsi6'] > $data[$index - 1]['rsi6'] + 10

                        && !$isWorkerDispatched

                    ) {
                        Log::info('LongWorkerMACD: Dispatching Long Thread MACD... Coin:  ' . $symbol);
                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'isWorkerDispatched' => true,
                        ]);
                        ThreadsMACDLongThread::dispatch($tradeInstance, $supportResistance);
                        break;
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('LongWorkerMACD: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
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
                'leverage' => 1,
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
                'leverage' => 1,
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
