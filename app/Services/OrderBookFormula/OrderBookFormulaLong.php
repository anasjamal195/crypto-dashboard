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
        // ==================New Strategy========================
        try {
            $workerLimit = 10;
            $openSymbols = DB::table('live_trades_future_results')
                ->where('trade_acc', $account)
                ->where('trade_status', 'open')
                ->pluck('symbol');

            // Subquery to get the latest snapshot entry per symbol with required conditions
            $fiveMinutesAgo = Carbon::now()->subMinutes(5);

            $latestSnapshots = DB::table('order_book_snapshots as obs1')
                ->select(
                    'obs1.symbol',
                    DB::raw('MAX(obs1.snapshot_time) as latest_snapshot_time')
                )
                ->where('obs1.snapshot_time', '>=', $fiveMinutesAgo)
                ->where('signal', 'SHORT')
                ->where('depth', 1000)
                ->where('short_strength', '>=', 8)
                ->groupBy('obs1.symbol');

            $triggers = DB::table('order_book_snapshots as obs2')
                ->joinSub($latestSnapshots, 'latest_obs', function ($join) {
                    $join->on('obs2.symbol', '=', 'latest_obs.symbol')
                        ->on('obs2.snapshot_time', '=', 'latest_obs.latest_snapshot_time');
                })
                ->join('trade_handler as th', function ($join) use ($account, $openSymbols) {
                    $join->on('obs2.symbol', '=', 'th.symbol')
                        ->where('th.position', 'LONG')
                        ->where('th.tradeAccount', $account)
                        ->whereNotIn('th.symbol', $openSymbols)
                        ->where('th.isWorkerDispatched', 0);
                })
                ->select(
                    'obs2.symbol',
                    'obs2.snapshot_time',
                    'obs2.resistance_levels',
                    'obs2.support_levels',
                    'obs2.signal',
                    'obs2.long_strength',
                    'obs2.short_strength',
                    'th.buyPrice',
                    'th.isWorkerDispatched',
                    'th.id as trade_handler_id',
                    'obs2.id as trigger_id',
                )
                ->get()->toArray();

            foreach ($triggers as $trigger) {
                $workers = DB::table('workers')->get();

                // Check for available workers and check next coins
                foreach ($workers as $worker) {
                    // If a worker is available than add its entry
                    if ($worker->symbol_count < $workerLimit && !$worker->trade_status) {

                        $trade_handler = DB::table('trade_handler')->where('id', $trigger->trade_handler_id)->get();
                        DB::table('worker_symbols')->insert(
                            [
                                'worker_id' => $worker->worker_id,
                                'symbol' => $trigger->symbol,
                                'trigger_id' => $trigger->trigger_id,
                                'trade_handler_id' => $trigger->trade_handler_id,
                            ]
                        );
                        DB::table('workers')->where('worker_id', $worker->worker_id)->update([
                            'symbol_count' => $worker->symbol_count + 1,
                            'active_status' => 1,
                        ]);
                        // Toggle Long trade handler for same coin
                        DB::table('trade_handler')->where('id', $trigger->trade_handler_id)->update([
                            'isWorkerDispatched' => true,
                        ]);

                        // Toggle Short trade handler for same coin
                        DB::table('trade_handler')->where('symbol', $trade_handler->symbol)->where('tradeAccount',$trade_handler->tradeAccount)->where('position','SHORT')->update([
                            'isWorkerDispatched' => true,
                        ]);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('LongWorkerOrderBook: Error - ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }

        // ======================================================

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
