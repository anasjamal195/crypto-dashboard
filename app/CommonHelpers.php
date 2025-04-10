<?php
// Support Resistance formula (80 Trades with 87% accuracy) 1.5 SL
namespace App;

use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\SupervisorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommonHelpers
{
    public static $binanceIntervals = [
        '1s'  => 1 / 60,  // 1 second (not commonly used)
        '1m'  => 1,       // 1 minute
        '3m'  => 3,       // 3 minutes
        '5m'  => 5,       // 5 minutes
        '15m' => 15,      // 15 minutes
        '30m' => 30,      // 30 minutes
        '1h'  => 60,      // 1 hour
        '2h'  => 120,     // 2 hours
        '4h'  => 240,     // 4 hours
        '6h'  => 360,     // 6 hours
        '8h'  => 480,     // 8 hours
        '12h' => 720,     // 12 hours
        '1d'  => 1440,    // 1 day
        '3d'  => 4320,    // 3 days
        '1w'  => 10080,   // 1 week
        '1M'  => 43200,   // 1 month (approx 30 days)
    ];
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

        $sellShortMACDConditions =

            $noLightCandle
            // && $data[$index]['per'] < 0
            // && $data[$index - 1]['per'] < 0
            && $data[$index]['histogram'] < $data[$index - 1]['histogram']
            // && $data[$index]['histogram'] > 0
            // $data[$index]['histogram'] > 0
            // && $isUpwardWick
            // && ($kdjCrossover || $kdjApproachingCrossover)
            // && $totalGreenCandles > 4
            // && $data[$index]['dif'] < $data[$index - 1]['dif']
            // &&
            // !(
            //     $data[$index]['dif'] > $data[$index]['dea']
            //     && $data[$index - 1]['dif'] < $data[$index - 1]['dea']
            // );
        ;

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



        $buyLongMACDConditions =

            $noDarkCandle
            // && $data[$index]['per'] > 0
            // && $data[$index - 1]['per'] > 0

            && $data[$index]['histogram'] > $data[$index - 1]['histogram']
            // && $data[$index]['histogram'] < 0


            // $data[$index]['histogram'] < 0
            // && $isDownwardWick
            // && ($kdjCrossover || $kdjApproachingCrossover)
            // && $totalRedCandles > 4
            // && $data[$index]['dif'] > $data[$index - 1]['dif']

            // &&
            // !(
            //     $data[$index]['dif'] < $data[$index]['dea']
            //     && $data[$index - 1]['dif'] > $data[$index - 1]['dea']
            // );
        ;

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


    public static function checkCompleteMACDShort($data, $index, $symbol)
    {


        $candle = $data[$index];
        $macdDarkGreenDistance = 0;
        $loopIndex = $index;

        while (true) {

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

            if ($data[$loopIndex]['histogram'] < 0)
                break;
            $totalGreenCandles++;

            $loopIndex--;
        }

        // $volumeCrossover = false;
        // $loopIndex = $index;

        // while (true) {

        //     if ($data[$loopIndex]['volumeMA5'] < $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] > $data[$loopIndex - 1]['volumeMA10']) {
        //         $volumeCrossover = true;
        //         break;
        //     }
        //     if ($data[$loopIndex]['volumeMA5'] > $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] < $data[$loopIndex - 1]['volumeMA10']) {
        //         break;
        //     }
        //     $loopIndex--;
        // }


        $kdjCrossover = false;
        $kdjthreshold = 0;
        $loopIndex = $index;

        while (true) {
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
            if ($data[$loopIndex]['high'] > $lastHighest) {
                $lastHighest = $data[$loopIndex]['high'];
            } else if ($data[$loopIndex]['high'] < $data[$index]['high'] || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }
        $difDeaCondition = $data[$index - 3]['dif'] < $data[$index - 3]['dea'] && $data[$index]['dif'] > $data[$index]['dea'];


        $maCondition = $data[$index]['ma7'] > $data[$index]['ma25'] && $data[$index - 5]['ma7'] > $data[$index - 5]['ma25'];


        if (
            // Current and previous MACD should be green
            $data[$index]['histogram'] > 0
            && $isUpwardWick
            && ($kdjCrossover || $kdjApproachingCrossover)
            && $totalGreenCandles > 4
            // && $data[$index]['per'] <= -0.2
            // && $data[$index]['per'] > -0.6
            // && $data[$index]['close'] < $lastHighest * (1 - 0.7 / 100)
            && $data[$index]['avl'] < $data[$index - 1]['avl']
            && $data[$index]['dif'] < $data[$index - 1]['dif']
            && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] - 10
            && $data[$index]['per'] < 0 && $data[$index - 1]['per'] > 0
            && $maCondition
            && !$difDeaCondition
        ) {







            // // New Conditions on 2h 
            // // Fetch 2-hour candlestick data
            // $data2h = BinanceApiService::getCandleStickDataPast($symbol, '2h', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle2h = end($data2h);
            // $secondLastCandle2h = prev($data2h);
            // $thirdLastCandle2h = prev($data2h);

            // // $instantOpen = false;

            // // // Condition 6: Skip trade if MA7 is above both MA25 and MA99, and previous candle's percentage change is non-positive
            // // if ($candle2h['ma7'] < $candle2h['ma25'] && $candle2h['ma7'] < $candle2h['ma99'] && $secondLastCandle2h['per'] >= 0) {
            // //     return false;
            // // }

            // // // Condition 5: Check for instant opening
            // // if (
            // //     ($candle2h['ma7'] < $candle2h['ma25'] && $secondLastCandle2h['ma7'] > $secondLastCandle2h['ma25']) ||
            // //     ($candle2h['ma7'] < $candle2h['ma99'] && $secondLastCandle2h['ma7'] > $secondLastCandle2h['ma99'])
            // // ) {
            // //     $instantOpen = true;
            // // }

            // // Calculate wick and solid region sizes
            // $upperWick = $secondLastCandle2h['high'] - max($secondLastCandle2h['close'], $secondLastCandle2h['open']);
            // $lowerWick = min($secondLastCandle2h['close'], $secondLastCandle2h['open']) - $secondLastCandle2h['low'];
            // $solidRegion = abs($secondLastCandle2h['close'] - $secondLastCandle2h['open']);

            // // // Skip trade if it's not an instant opening and doesn't meet Conditions 1, 3, or 4
            // // if (
            // //     !$instantOpen &&
            // //     !(
            // //         $secondLastCandle2h['per'] <= 0.15 || // Condition 1
            // //         ($secondLastCandle2h['per'] > 0 && $lowerWick < $upperWick && $lowerWick < $solidRegion * 0.1) || // Condition 3
            // //         ($lowerWick == 0 && $upperWick > 0) // Condition 4
            // //     )
            // // ) {
            // //    return false;
            // // }

            // // // Condition 2: Final check - skip trade if percentage change is positive and upper wick is greater than lower wick
            // // if ($secondLastCandle2h['per'] < 0 && $upperWick < $lowerWick) {
            // //     return false;
            // // }


            // // Custom Condition
            // if (
            //     !(

            //         $secondLastCandle2h['histogram'] < $thirdLastCandle2h['histogram']
            //     )
            // ) {
            //     return false;
            // }
            // // dd($candle, $secondLastCandle2h, $thirdLastCandle2h, $symbol);


            // $data15m = BinanceApiService::getCandleStickDataPast($symbol, '15m', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle15m = end($data15m);
            // $secondLastCandle15m = prev($data15m);
            // $thirdLastCandle15m = prev($data15m);


            // if (

            //     $secondLastCandle15m['histogram'] > $thirdLastCandle15m['histogram'] && $secondLastCandle15m['histogram'] < 0

            // ) {
            //     return false;
            // }


            // $data1h = BinanceApiService::getCandleStickDataPast($symbol, '1h', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle1h = end($data1h);
            // $secondLastCandle1h = prev($data1h);
            // $thirdLastCandle1h = prev($data1h);
            // $fourthLastCandle1h = prev($data1h);
            // $fifthLastCandle1h = prev($data1h);


            // if (
            //     (
            //         $secondLastCandle1h['per'] > 0
            //         && $thirdLastCandle1h['per'] > 0
            //         && $fourthLastCandle1h['per'] > 0
            //         // && $fifthLastCandle1h['per'] > 0
            //     )
            // ) {
            //     return false;
            // }

            // $data4h = BinanceApiService::getCandleStickDataPast($symbol, '4h', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle4h = end($data4h);
            // $secondLastCandle4h = prev($data4h);
            // $thirdLastCandle4h = prev($data4h);
            // $fourthLastCandle4h = prev($data4h);
            // $fifthLastCandle4h = prev($data4h);

            // if (
            //     $candle4h['per'] < -0.25
            // ) {
            //     return false;
            // }


            return true;
        } else {
            return false;
        }
    }

    public static function checkCompleteMACDLong($data, $index, $symbol)
    {

        $candle = $data[$index];
        $macdLightRedDistance = 0;
        $loopIndex = $index;

        while (true) {

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
            if ($data[$loopIndex]['low'] < $lastLowest) {
                $lastLowest = $data[$loopIndex]['low'];
            } else if ($data[$loopIndex]['low'] > $data[$index]['low'] || $loopIndex == 1) {
                break;
            }
            $loopIndex--;
        }

        $difDeaCondition = $data[$index - 3]['dif'] > $data[$index - 3]['dea'] && $data[$index]['dif'] < $data[$index]['dea'];


        $maCondition = $data[$index]['ma7'] < $data[$index]['ma25'] && $data[$index - 5]['ma7'] < $data[$index - 5]['ma25'];

        if (
            // Current and previous MACD should be red

            $data[$index]['histogram'] < 0
            && $isDownwardWick
            && ($kdjCrossover || $kdjApproachingCrossover)
            && $totalRedCandles > 4
            // && $data[$index]['per'] >= 0.2
            // && $data[$index]['per'] < 0.6
            // && $data[$index]['close'] > $lastLowest * (1 + 0.7 / 100)
            && $data[$index]['avl'] > $data[$index - 1]['avl']
            && $data[$index]['dif'] > $data[$index - 1]['dif']
            && $data[$index]['rsi6'] > $data[$index - 1]['rsi6'] + 10
            && $data[$index]['per'] > 0 && $data[$index - 1]['per'] < 0
            && $maCondition
            && !$difDeaCondition
        ) {
            // Fetch 2-hour candlestick data
            // $data2h = BinanceApiService::getCandleStickDataPast($symbol, '2h', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle2h = end($data2h);
            // $secondLastCandle2h = prev($data2h);
            // $thirdLastCandle2h = prev($data2h);



            // // Calculate wick and solid region sizes
            // $upperWick = $secondLastCandle2h['high'] - max($secondLastCandle2h['close'], $secondLastCandle2h['open']);
            // $lowerWick = min($secondLastCandle2h['close'], $secondLastCandle2h['open']) - $secondLastCandle2h['low'];
            // $solidRegion = abs($secondLastCandle2h['close'] - $secondLastCandle2h['open']);

            // // // Skip trade if it's not an instant opening and doesn't meet Conditions 1, 3, or 4
            // // if (
            // //     !$instantOpen &&
            // //     !(
            // //         $secondLastCandle2h['per'] >= 0.15 || // Condition 1
            // //         ($secondLastCandle2h['per'] < 0 && $lowerWick > $upperWick && $upperWick < $solidRegion * 0.1) || // Condition 3
            // //         ($upperWick == 0 && $lowerWick > 0) // Condition 4
            // //     )
            // // ) {
            // //     return false;
            // // }

            // // // Condition 2: Final check - skip trade if percentage change is positive and upper wick is greater than lower wick
            // // if ($secondLastCandle2h['per'] > 0 && $upperWick > $lowerWick) {
            // //     return false;
            // // }


            // if (
            //     !(
            //         $secondLastCandle2h['histogram'] > $thirdLastCandle2h['histogram']
            //     )
            // ) {
            //     return false;
            // }

            // $data15m = BinanceApiService::getCandleStickDataPast($symbol, '15m', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle15m = end($data15m);
            // $secondLastCandle15m = prev($data15m);
            // $thirdLastCandle15m = prev($data15m);




            // if (

            //     $secondLastCandle15m['histogram'] < $thirdLastCandle15m['histogram'] && $secondLastCandle15m['histogram'] > 0

            // ) {
            //     return false;
            // }
            // $data1h = BinanceApiService::getCandleStickDataPast($symbol, '1h', 100, $candle['binance_timestamp'], 'FUTURE');
            // $candle1h = end($data1h);
            // $secondLastCandle1h = prev($data1h);
            // $thirdLastCandle1h = prev($data1h);
            // $fourthLastCandle1h = prev($data1h);
            // $fifthLastCandle1h = prev($data1h);

            // if (
            //     (
            //         $secondLastCandle1h['per'] < 0
            //         && $thirdLastCandle1h['per'] < 0
            //         && $fourthLastCandle1h['per'] < 0
            //         // && $fifthLastCandle1h['per'] > 0
            //     )
            // ) {
            //     return false;
            // }

            return true;
        } else {
            return false;
        }
    }




    public static function getVolumeSignals($symbol, $interval, $isArr = true)
    {
        $parentLimit = 1000;
        $isProcessed =  false;
        $data = BinanceApiService::getCandleStickData($symbol, $interval, $parentLimit, null, 'FUTURE', $isProcessed);


        $intervalToMins = self::$binanceIntervals[$interval];
        $timestamp = $data[0][0] - (60 * $intervalToMins * 1000 * 300);
        $averageAdjustmetCandles =  BinanceApiService::getCandleStickData($symbol, $interval, 300, $timestamp, 'FUTURE', $isProcessed);
        $triggers = [];
        foreach (array_merge($averageAdjustmetCandles, $data) as $index => $candle) {

            if ($index < 300) {
                continue;
            }
            $timestamp = $candle[0] / 1000;
            $date = new \DateTime("@{$timestamp}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $timestamp =  $date->format('Y-m-d H:i:s');




            // Now create seperate objects for each candle of each symbol
            // Prepare Data for this candle

            $start = 0;
            $length = $index - $start + 1;

            $subArray = array_slice($data, $start, $length);
            $volumeSignalService = new BinanceVolumeIndicatorsService([
                'symbols' => [$symbol],
                'data' => $subArray,
                'timeframes' => [$interval], // Suitable for scalping
                'target_profit' => 0.5, // Your 0.5% target
                'use_obv' => true,
                'use_vwap' => true,
                'use_volume_profile' => false,
                'use_cvd' => true,
                'use_mfi' => true,
            ]);


            $signal = $volumeSignalService->getScalpingSignals();
            $signal = $signal['signals'][0];
            $signal['timestampReadable'] = $timestamp;
            $signal['timestamp'] = $candle[0];
            // if ($signal['potential'])

            if ($isArr)
                $triggers[] = $signal;
            else
                $triggers[$timestamp] = $signal;
        }
        return $triggers;
    }




    public static function getVolumeSignalsRealTime($symbol, $interval)
    {
        $parentLimit = 300;
        $isProcessed =  false;
        $data = BinanceApiService::getCandleStickData($symbol, $interval, $parentLimit, null, 'FUTURE', $isProcessed);



        $volumeSignalService = new BinanceVolumeIndicatorsService([
            'symbols' => [$symbol],
            'data' => $data,
            'timeframes' => [$interval], // Suitable for scalping
            'target_profit' => 0.5, // Your 0.5% target
            'use_obv' => true,
            'use_vwap' => true,
            'use_volume_profile' => true,
            'use_cvd' => true,
            'use_mfi' => true,
        ]);


        $signal = $volumeSignalService->getScalpingSignals();
        $signal = $signal['signals'][0];
        return $signal;
    }



    public static function changeCoinStatus($coin, $status)
    {
        // change status for this entry
        DB::table('coins')->where('symbol', $coin)->update([
            'status' => $status,
        ]);


        // Add snapshot in history table
        DB::table('coin_status_history')->insert([
            'symbol' => $coin,
            'status' => $status,
            'timestamp' => Carbon::now()->toDateTimeString(),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),

        ]);
    }


    public static function addNewCoin($coin)
    {
        $systemCoin = DB::table('coins')->where('symbol', $coin)->first();
        if ($systemCoin) {
            return false;
        }

        // Insert New entry entry
        DB::table('coins')->insert([
            'symbol' => $coin,
            'status' => 'T',
            'market' => 'FUTURE',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
}
