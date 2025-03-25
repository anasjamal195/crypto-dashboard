<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For Long Trades in future market
Will Target Long Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\OrderBookFormula;

use App\CommonHelpers;
use App\Jobs\Threads\LongThread;
use App\Jobs\ThreadsMACD\LongThread as ThreadsMACDLongThread;
use App\Jobs\ThreadsOrderBook\LongThread as ThreadsOrderBookLongThread;
use App\Models\OrderBookSnapshot;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderBookFormulaLong
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

                $openWorkersCount = DB::table('trade_handler')->where('isWorkerDispatched', true)->count();
                if ($openWorkersCount >= 10) {
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

                    $allowOpening = false;
                    $triggerPrice = 0;

                    $timestamp = $data[$index]['timestamp'];
                    $snapshot = OrderBookSnapshot::where('snapshot_time', '>=', $timestamp)
                        ->where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                        ->where('symbol', $symbol)
                        ->where('depth', 1000)
                        ->where('signal', 'SHORT')
                        ->where('short_strength', '>=', 8)
                        ->latest('snapshot_time')
                        ->first();

                    if (!$snapshot) {
                        continue;
                    }

                    $entry_points = array_map(function ($level) {
                        return $level['price'];
                    }, $snapshot->support_levels);

                    $triggerPrice = max($entry_points);
                    $triggerIndex = $index;


                    if (
                        $allowOpening
                        &&
                        !(
                            $data[$index]['dif'] < $data[$index]['dea']
                            && $data[$index - 1]['dif'] > $data[$index - 1]['dea']
                        )

                    ) {
                        Log::info('LongWorkerMACD: Dispatching Long Thread MACD... Coin:  ' . $symbol);
                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'isWorkerDispatched' => true,
                        ]);
                        ThreadsOrderBookLongThread::dispatch($tradeInstance, $supportResistance, $triggerPrice, $triggerIndex);
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
