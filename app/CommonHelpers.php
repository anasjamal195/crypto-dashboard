<?php

namespace App;

use App\Services\BinanceApiService;
use App\Services\SupervisorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommonHelpers
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}
    public static function getSettingsValue($setting_key, $default)
    {
        return DB::table('trade_settings')->where('settings_key', $setting_key)->first()->settings_value ?? $default;
    }
    public static function getMetaValue($id, $meta_key, $default)
    {
        return DB::table('user_meta')->where('user_id', $id)->where('meta_key', $meta_key)->first()->meta_value ?? $default;
    }
    public static function getIndicatorAverages($symbol, $interval, $market)
    {
        $columns = [
            'volume',
            'ma7',
            'ma14',
            'ma25',
            'ma99',
            'rsi6',
            'per',
            'dif',
            'dea',
            'histogram',
            'sar',
            'obv',
            'stoch_rsi',
            'stoch_k',
            'stoch_d',
            'previousObvHigh',
            'wr',
            'K',
            'D',
            'J'
        ];

        $averages = [];

        foreach ($columns as $column) {
            $averages[$column] = DB::table('ideal_buying_candles')->where('symbol', $symbol)->where('interval', $interval)->where('market', $market)->avg($column);
        }
        return $averages;
    }
    public static function getPriorityQueue($interval, $market, $limit)
    {

        $tradeData = DB::table('coin_reports as main')
            ->select(
                'main.symbol',
                DB::raw('COUNT(main.id) as total_entries'),              // Total number of entries per symbol
                DB::raw('SUM(main.profit) as total_profit'),             // Sum of profit per symbol
                DB::raw('AVG(main.profit) as average_profit'),           // Average profit per symbol
                DB::raw('AVG(main.duration) as average_duration'),       // Average duration per symbol
                DB::raw('SUM(main.duration) as total_duration'),         // Total duration per symbol
                DB::raw('MAX(main.profit) as max_profit'),               // Maximum profit per symbol
                DB::raw('MIN(main.profit) as min_profit'),               // Minimum profit per symbol
                DB::raw('MAX(main.lowestPricePercentage) as max_lowestPrice'),  // Maximum of lowestPrice per symbol
                DB::raw('MIN(main.lowestPricePercentage) as min_lowestPrice'),   // Minimum of lowestPrice per symbol
                DB::raw('MAX(main.created_at) as last_updated')          // Last updated time for each symbol
            )
            ->where('main.market', $market)
            ->where('main.interval', $interval)
            ->where('position', 'LONG')
            ->groupBy('main.symbol')
            ->orderBy('total_duration', 'ASC')
            ->orderBy('average_profit', 'ASC')
            ->orderBy('max_lowestPrice', 'ASC')
            ->limit($limit)
            ->get();

        // $tradeData = DB::table('coins')->limit($limit)->get();
        return $tradeData;
    }
    public static function getOpenOrderCount($interval, $market, $trade_acc)
    {
        if ($market === 'SPOT')
            return  DB::table('orders')
                ->where('interval', $interval)
                ->where('trade_acc', $trade_acc)
                ->where('market', $market)
                ->where('trade_status', 'open')
                ->where('side', 'BUY')
                ->count();
        else
            return  DB::table('live_trades_future_results')
                ->where('trade_acc', $trade_acc)
                ->where('currentProfit', '<= ', 1)
                ->where('trade_status', 'open')
                ->count();
    }
    public static function checkLosses($symbol, $market, $trade_acc, $formula)
    {
        $totalLoss = DB::table('live_trades_future_results')
            ->where('symbol', $symbol)
            ->where('trade_acc', $trade_acc)
            ->where('trade_status', 'close')
            ->where('created_at', '>=', Carbon::now()->subHours(12)->format('Y-m-d H:i:s'))
            ->sum('currentProfit', '<=', $formula);
        $open_orders = DB::table('live_trades_future_results')
            ->where('symbol', $symbol)
            ->where('trade_acc', $trade_acc)
            ->where('trade_status', 'open')
            ->first();
        if ($totalLoss >= 2.5 && !$open_orders) {
            if ($formula === 'RSI Formula') {
                SupervisorService::stop('acc_2_rsi_short_worker');
                SupervisorService::stop('acc_2_rsi_short_worker');
            } else if ($formula === 'VSA Formula') {
                SupervisorService::stop('acc_2_vsa_short_worker');
                SupervisorService::stop('acc_2_vsa_short_worker');
            }
        }
    }
    public static function checkOpenOrder($symbol, $interval, $market, $trade_acc)
    {
        if ($market === 'SPOT') {
            $open_orders =  DB::table('orders')
                ->where('symbol', $symbol)
                ->where('trade_acc', $trade_acc)
                ->where('market', $market)
                ->where('trade_status', 'open')
                ->where('side', 'BUY')
                ->get();

            $open_orders = json_decode(json_encode($open_orders), true);
            if (empty($open_orders)) {
                return ['is_open' => false];
            } else {
                return ['is_open' => true, 'order' => $open_orders[0]];
            }
        } else if ($market === 'FUTURE') {
            $open_orders =  DB::table('live_trades_future_results')
                ->where('symbol', $symbol)
                ->where('position', $interval)
                ->where('trade_acc', $trade_acc)
                ->where('trade_status', 'open')
                ->get();

            $open_orders = json_decode(json_encode($open_orders), true);
            if (empty($open_orders)) {
                return ['is_open' => false];
            } else {
                return ['is_open' => true, 'order' => $open_orders[0]];
            }
        }
    }

    // Check if current instance falls under wick category for a specific percentage
    public static function isCandleWick($candle, $type = 'upper', $wickBuffer = 20, $thresholdPrice, $symbol,  $priceCount = 20)
    {

        $prices = [];


        for ($i = 0; $i < $priceCount; $i++) {
            $prices[] = BinanceApiService::getCurrentPrice($symbol, 'FUTURE');
            self::delayS(1);
        }

        $median = self::calculateMedian($prices);
        $mode = self::calculateMode($prices);

        // Define a threshold for the concentrated zone (e.g., 0.3% of the median)
        $threshold = 0.003 * $median;



        // New Strategy using centeral values and candle weights average
        if ($type === 'upper') {


            // Check if the last entry is an extrema

            if ($median >= $thresholdPrice) {
                return false;
            } else {
                return true;
            }
        } else if ($type === 'lower') {
            if ($median <= $thresholdPrice) {
                return false;
            } else {
                return true;
            }
        }


        // if ($type === 'upper') {

        //     $difference = $candle['high'] - $candle['low'];

        //     $diffPercentage = $difference * $wickBuffer / 100;

        //     if ($candle['close'] <= $candle['high'] && $candle['close'] >= ($candle['high'] - $diffPercentage)) {
        //         self::delayS(5);
        //         $currentPrice = BinanceApiService::getCurrentPrice($symbol, 'FUTURE');
        //         if ($currentPrice < $thresholdPrice) {
        //             return false;
        //         }
        //         return true;
        //     } else {
        //         return false;
        //     }
        // } else if ($type === 'lower') {

        //     $difference = $candle['high'] - $candle['low'];

        //     $diffPercentage = $difference * $wickBuffer / 100;

        //     if ($candle['close'] >= $candle['low'] && $candle['close'] <= ($candle['low'] + $diffPercentage)) {
        //         self::delayS(5);
        //         $currentPrice = BinanceApiService::getCurrentPrice($symbol, 'FUTURE');
        //         if ($currentPrice > $thresholdPrice) {
        //             return false;
        //         }
        //         return true;
        //     } else {
        //         return false;
        //     }
        // }
    }

    /**
     * Calculate the median of an array.
     *
     * @param array $arr
     * @return float
     */
    public static function calculateMedian($arr)
    {
        if (empty($arr)) {
            return 0;
        }

        sort($arr);
        $count = count($arr);

        if ($count % 2 == 0) {
            $middle1 = $arr[($count / 2) - 1];
            $middle2 = $arr[$count / 2];
            return ($middle1 + $middle2) / 2;
        } else {
            return $arr[floor($count / 2)];
        }
    }

    /**
     * Calculate the mode of an array.
     *
     * @param array $arr
     * @return float
     */
    public static function calculateMode($arr)
    {
        if (empty($arr)) {
            return 0;
        }

        $frequency = array_count_values($arr);
        $maxFrequency = max($frequency);
        $modes = array_keys($frequency, $maxFrequency);
        return $modes[0];
    }
    public static function delayMS($ms)
    {
        usleep($ms * 1000);
    }
    public static function delayS($s)
    {
        usleep($s * 1000 * 1000);
    }
    public static function delayMin($m)
    {
        usleep($m * 60 * 1000 * 1000);
    }

    public static function prettyEcho(...$data)
    {
        echo "<pre>";
        foreach ($data as $item) {
            if (is_array($item) || is_object($item)) {
                print_r($item);
            } else {
                echo $item;
            }
            echo "\n";
        }
        echo "</pre>";
    }

    public static function prettyLog(...$data)
    {
        $log = "";
        foreach ($data as $item) {
            if (is_array($item) || is_object($item)) {
                $log .= print_r($item, true);
            } else {
                $log .= $item;
            }
            $log .= "\n";
        }
        Log::info($log);
    }


    public static function clearLogs()
    {
        // Execute the shell command to truncate the log file
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            exec("truncate -s 0 {$logFile}");
            return response()->json(['message' => 'Log file cleared successfully']);
        }
        return response()->json(['message' => 'Log file does not exist'], 404);
    }
    public static function distributeEntriesToWorkers($triggers, $numWorkers = 10)
    {
        $totalEntries = count($triggers);

        // If entries are less than or equal to workers, assign one per worker
        if ($totalEntries <= $numWorkers) {
            return array_chunk($triggers, 1);
        }

        // Otherwise, distribute entries optimally
        $entriesPerWorker = ceil($totalEntries / $numWorkers);

        return array_chunk($triggers, $entriesPerWorker);
    }

    public static function getPercentDiff($pivot, $value)
    {
        return (abs($pivot - $value) / $pivot) * 100;
    }




    public static function checkMacdConditionsShort($data, $index)
    {
        $macdDarkGreenDistance = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] <= $data[$loopIndex - 1]['histogram']) {
                $macdDarkGreenDistance++;
            } else {
                break;
            }

            $loopIndex--;
        }


        $totalGreenCandles = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] < 0)
                break;
            $totalGreenCandles++;

            $loopIndex--;
        }



        $kdjCrossover = false;
        $kdjthreshold = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if (
                $data[$loopIndex]['J'] < $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100) &&
                $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100)
                &&
                $data[$loopIndex]['J'] < $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100) &&
                $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100)
            ) {
                $kdjCrossover = true;
                break;
            }

            if (
                ($data[$loopIndex]['J'] > $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100) &&
                    $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100)
                    &&
                    $data[$loopIndex]['J'] > $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100) &&
                    $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100))
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
        $upperWick = ($data[$index]['high'] - $data[$index]['open']);
        $lowerWick = ($data[$index]['close'] - $data[$index]['low']);
        $isUpwardWick = $data[$index]['close'] < $data[$index]['open'] && $upperWick > $lowerWick * 2;


        $lastHighest = $data[$index]['high'];
        $loopIndex = $index;



        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['high'] > $lastHighest) {
                $lastHighest = $data[$loopIndex]['high'];
            } else if ($data[$loopIndex]['high'] < $data[$index]['high'] || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }



        $noLightCandle = true;
        $loopIndex = $index - 1;



        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram']) {
                $noLightCandle = false;
            } else if ($data[$loopIndex]['histogram'] < 0 || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }

// without green candles > 3 condition gives 85% with 81 trades 1.5 SL

        $sellShortMACDConditions = $data[$index]['histogram'] > 0
            && $isUpwardWick
            && ($kdjCrossover || $kdjApproachingCrossover)
            // && $totalGreenCandles > 3
            && $noLightCandle
            &&
            !(
                $data[$index]['dif'] > $data[$index]['dea']
                && $data[$index - 1]['dif'] < $data[$index - 1]['dea']
            );;

        return $sellShortMACDConditions;
    }


    public static function checkMacdConditionsLong($data, $index)
    {
        $macdLightRedDistance = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] >= $data[$loopIndex - 1]['histogram']) {
                $macdLightRedDistance++;
            } else {
                break;
            }

            $loopIndex--;
        }


        $totalRedCandles = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] > 0)
                break;

            if ($data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'])
                $totalRedCandles++;

            $loopIndex--;
        }




        $kdjCrossover = false;
        $kdjthreshold = 0;
        $loopIndex = $index;

        while (true) {
            if ($loopIndex == 0) {
                break;
            }
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
        $isUpwardWick = $data[$index]['close'] > $data[$index]['open'] && $upperWick > $lowerWick * 2;
        $isDownwardWick = $data[$index]['close'] > $data[$index]['open'] && $lowerWick > $upperWick * 2;

        $lastLowest = $data[$index]['low'];
        $loopIndex = $index;
        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['low'] < $lastLowest) {
                $lastLowest = $data[$loopIndex]['low'];
            } else if ($data[$loopIndex]['low'] > $data[$index]['low'] || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }

        $noDarkCandle = true;
        $loopIndex = $index - 1;



        while (true) {
            if ($loopIndex == 0) {
                break;
            }
            if ($data[$loopIndex]['histogram'] > $data[$loopIndex - 1]['histogram']) {
                $noDarkCandle = false;
            } else if ($data[$loopIndex]['histogram'] > 0 || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }

        //  ======================================



        $buyLongMACDConditions = $data[$index]['histogram'] < 0
            && $isDownwardWick
            && ($kdjCrossover || $kdjApproachingCrossover)
            // && $totalRedCandles > 3
            && $noDarkCandle
            &&
            !(
                $data[$index]['dif'] < $data[$index]['dea']
                && $data[$index - 1]['dif'] > $data[$index - 1]['dea']
            );


        return $buyLongMACDConditions;
    }

    public static function getTradeHandler($symbol, $account, $position)
    {
        return DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $account)->where('position', $position)->where('interval', '5m')->first();
    }

    public static function workerEngageSymbol($workerId, $triggerId, $symbol, $trade_acc, $position = '')
    {

        // Engage symbol in trade Handler
        if ($position) {
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->update([
                'isWorkerDispatched' => false,
            ]);
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->where('position', $position)->update([
                'isWorkerDispatched' => true,
            ]);
        } else {
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->update([
                'isWorkerDispatched' => true,
            ]);
        }


        // Add entry in Worker_symbol Table
        DB::table('worker_symbols')->insert(
            [
                'worker_id' => $workerId,
                'symbol' => $symbol,
                'trigger_id' => $triggerId,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]
        );

        // Add entry in worker table
        DB::table('workers')->where('worker_id', $workerId)->update([
            'symbol_count' =>  DB::table('worker_symbols')->where('worker_id', $workerId)->count(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
    public static function workerFreeSymbol($workerId, $symbol, $account)
    {
        // Remove entry from worker Symbol
        DB::table('worker_symbols')->where('worker_id', $workerId)->where('symbol', $symbol)->delete();

        // Update Trade handler table
        DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $account)->update([
            'isWorkerDispatched' => false,
        ]);

        // Update Workers table count
        DB::table('workers')->where('worker_id', $workerId)->update([
            'symbol_count' => DB::table('worker_symbols')->where('worker_id', $workerId)->count(),
            'trade_status' => false,
            'updated_at' => Carbon::now()->toDateTimeString(),

        ]);
    }


    public static function workerFreeAllSymbols($workerId, $account = null)
    {
        $currentWorkerSymbols = DB::table('worker_symbols')->where('worker_id', $workerId)->pluck('symbol');


        if ($account) {
            DB::table('trade_handler')->where('tradeAccount', $account)->whereIn('symbol', $currentWorkerSymbols)->update([
                'isWorkerDispatched' => false,
            ]);
        } else {
            DB::table('trade_handler')->whereIn('symbol', $currentWorkerSymbols)->update([
                'isWorkerDispatched' => false,
            ]);
        }


        // Empty Worker Symbols for this worker
        DB::table('worker_symbols')->where('worker_id', $workerId)->delete();

        // Update Worker Status for zero symbols
        DB::table('workers')->where('worker_id', $workerId)->update([
            'symbol_count' => 0,
            'trade_status' => 0,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
    public static function workerEngageSymbolOpenTrade($workerId, $tradeHandler)
    {

        $symbol = $tradeHandler->symbol;
        $account = $tradeHandler->tradeAccount;

        $triggerId = DB::table('worker_symbols')->where('worker_id', $workerId)->where('symbol', $symbol)->first();



        // Disengage all symbols from current worker
        self::workerFreeAllSymbols($workerId, $account);


        // Update Worker Symbol Table
        DB::table('worker_symbols')->insert(
            [
                'worker_id' => $workerId,
                'symbol' => $symbol,
                'trigger_id' => $triggerId->id,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]
        );

        // Update Worker table
        DB::table('workers')->where('worker_id', $workerId)->update([
            'symbol_count' => 1,
            'trade_status' => true,
            'active_status' => true,
        ]);


        // Update Trade Handler Table
        DB::table('trade_handler')->where('id', $tradeHandler->id)->update([
            'isWorkerDispatched' => true,
        ]);
    }
}
