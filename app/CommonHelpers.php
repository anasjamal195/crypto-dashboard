<?php
// Support Resistance formula (80 Trades with 87% accuracy) 1.5 SL
namespace App;

use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\MailerService;
use App\Services\SupervisorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
    public static $candleDataKeysCoinReports = [
        // 'volume' => 'Volume',
        // 'volumeMA5' => 'Volume MA 5',
        // 'volumeMA10' => 'Volume MA 10',
        // 'avl' => 'AVL',
        // 'ma7' => 'MA 7',
        // 'ma14' => 'MA 14',
        // 'ma25' => 'MA 25',
        // 'ma99' => 'MA 99',

        'rsi6' => 'RSI 6',
        'stoch_k' => 'Stoch K',
        'stoch_d' => 'Stoch D',
        'wr' => 'WR',

        'dif' => 'DIF',
        'dea' => 'DEA',
        'histogram' => 'Histogram',

        'bb_middle' => 'BB Middle',
        'bb_upper' => 'BB Upper',
        'bb_lower' => 'BB Lower',

        // 'should_buy' => 'Should Buy',
        // 'should_sell' => 'Should Sell',
        // 'obv' => 'OBV',
        // 'cvd' => 'CVD',

        // 'vwap' => 'VWAP',
        // 'stoch_rsi' => 'Stochastic RSI',

        'K' => 'K',
        'D' => 'D',
        'J' => 'J',
        // 'previousObvHigh' => 'Previous OBV High',
        // 'previousObvLow' => 'Previous OBV Low',
        'per' => 'PER',
        'sar' => 'SAR',
        'mfi' => 'MFI',
        'adx' => 'ADX',
        'di_plus' => 'DI+',
        'di_minus' => 'DI−',
        // 'ema12' => 'EMA 12',
        // 'ema26' => 'EMA 26',
        'timestampReadable' => 'Time',

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

        $tableName = $market === 'FUTURE' ? 'live_trades_future_results' : 'live_trades_spot_results';
        $open_orders =  DB::table($tableName)
            ->where('symbol', $symbol)
            ->where('position', $interval)
            ->where('market', $market)
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

    public static function getPercentDiff($pivot, $value, $signed = false)
    {
        // Avoid divide-by-zero by using a very small number or explicitly returning null/0
        $denominator = abs($pivot) > 0 ? abs($pivot) : 0.00000001;

        if ($signed) {
            return (($value - $pivot) / $denominator) * 100;
        } else {
            return (abs($value - $pivot) / $denominator) * 100;
        }
    }

    public static function getCandleFromData($data, $binance_timestamp)
    {
        foreach ($data as $index => $entry) {
            if (isset($entry['binance_timestamp']) && $entry['binance_timestamp'] == $binance_timestamp) {
                return [
                    'candle' => $entry,
                    'index' => $index
                ];
            }
        }
        return null; // Return null if no matching entry is found
    }

    // public static function getPercentDiff($pivot, $value)
    // {
    //     return (abs($pivot - $value) / max(0.00000001, $pivot)) * 100;
    // }



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

    public static function getTradeHandler($symbol, $account, $position, $interval)
    {
        return DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $account)->where('position', $position)->where('interval', $interval)->first();
    }

    public static function workerEngageSymbol($workerId, $triggerId, $symbol, $trade_acc, $interval, $position = '')
    {

        // Engage symbol in trade Handler
        if ($position) {
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', $interval)->update([
                'isWorkerDispatched' => false,
            ]);
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', $interval)->where('position', $position)->update([
                'isWorkerDispatched' => true,
            ]);
        } else {
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', $interval)->update([
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




    public static function getVolumeSignals($symbol, $interval, $isArr = true, $timestamp = null, $parentLimit = 1000)
    {

        $isProcessed =  false;
        $data = BinanceApiService::getCandleStickData($symbol, $interval, $parentLimit, $timestamp, 'FUTURE', $isProcessed);

        $intervalToMins = self::$binanceIntervals[$interval];
        $timestamp = $data[0][0] - (60 * $intervalToMins * 1000 * 300);
        $adjustmentCandles =  BinanceApiService::getCandleStickData($symbol, $interval, 300, $timestamp, 'FUTURE', $isProcessed);
        $merged = [...$adjustmentCandles, ...$data];

        $triggers = [];

        foreach ($merged as $index => $candle) {

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
            $length = $index + 1;

            $subArray = array_slice($merged, $start, $length);

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


            unset($volumeSignalService);
        }


        return array_slice($triggers, -$parentLimit);
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
    public static function getLatestLog($action)
    {
        return DB::table('safety_logs')->where('action', $action)->orderBy('created_at', 'DESC')->first();
    }
    public static function addSafetyLog($action, $description = 'Started error Logging')
    {

        $id = DB::table('safety_logs')->insertGetId([
            'action' => $action,
            'details' => $description,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
        $log =  DB::table('safety_logs')->find($id); // Returns the newly inserted row
        MailerService::sendSafetyAlert($log);
        return $log;
    }

    public static function killTraderProcess($process = null)
    {

        if ($process === 'LONG') {
            DB::table('trade_settings')->where('settings_key', 'enable_long_multithread')->update([
                'settings_value' => false
            ]);
        } else if ($process === 'SHORT') {
            DB::table('trade_settings')->where('settings_key', 'enable_short_multithread')->update([
                'settings_value' => false
            ]);
        } else {

            DB::table('trade_settings')->where('settings_key', 'enable_long_multithread')->update([
                'settings_value' => false
            ]);

            DB::table('trade_settings')->where('settings_key', 'enable_short_multithread')->update([
                'settings_value' => false
            ]);
        }
    }

    public static function startTraderProcess($process = null)
    {

        if ($process === 'LONG') {
            DB::table('trade_settings')->where('settings_key', 'enable_long_multithread')->update([
                'settings_value' => true
            ]);
        } else if ($process === 'SHORT') {
            DB::table('trade_settings')->where('settings_key', 'enable_short_multithread')->update([
                'settings_value' => true
            ]);
        } else {

            DB::table('trade_settings')->where('settings_key', 'enable_long_multithread')->update([
                'settings_value' => true
            ]);

            DB::table('trade_settings')->where('settings_key', 'enable_short_multithread')->update([
                'settings_value' => true
            ]);
        }
    }


    // Trade indicator analysis table helpers

    public static function insertIndicatorLogs($symbol, $currentTrade, $indicatorMeta = [])
    {
        return DB::table('indicator_analysis')->insert([
            'symbol' => $symbol,
            'indicator_meta' => json_encode($indicatorMeta),
            'trade_profit' =>  $currentTrade['profit'],
            'trade_duration' =>  $currentTrade['duration'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function truncateIndicatorLogs()
    {
        return DB::table('indicator_analysis')->truncate();
    }




    // BOLLINGER BAND ANALYSIS FUNCTIONS

    /**
     * Analyzes Bollinger Bands to identify potential swing trading opportunities
     * 
     * @param array $data Array of candle data with OHLC and Bollinger Band values
     * @param int $currentIndex The index of the current candle to analyze
     * @param int $lookbackPeriod Number of previous candles to analyze for context
     * @return array Analysis results with swing probabilities and signals
     */
    public static function analyzeBollingerBandSwing(array $data, int $currentIndex, int $lookbackPeriod = 5): array
    {

        // Ensure we have enough data to perform the analysis
        if ($currentIndex < $lookbackPeriod || !isset($data[$currentIndex])) {
            return [
                'signal' => 'neutral',
                'long_probability' => 0,
                'short_probability' => 0,
                'message' => 'Insufficient data for analysis'
            ];
        }

        // Extract current candle data
        $currentCandle = $data[$currentIndex];
        $prevCandle = $data[$currentIndex - 1];

        // Calculate Bollinger Band metrics
        $bbWidth = $currentCandle['bb_upper'] - $currentCandle['bb_lower'];
        if (!$bbWidth) {
            return [
                'signal' => 'neutral',
                'long_probability' => 0,
                'short_probability' => 0,
                'message' => 'Division by zero'
            ];
        }
        $prevBbWidth = $prevCandle['bb_upper'] - $prevCandle['bb_lower'];
        if (!$prevBbWidth) {
            return [
                'signal' => 'neutral',
                'long_probability' => 0,
                'short_probability' => 0,
                'message' => 'Division by zero'
            ];
        }
        $percentB = ($currentCandle['close'] - $currentCandle['bb_lower']) / $bbWidth * 100;
        $prevPercentB = ($prevCandle['close'] - $prevCandle['bb_lower']) / $prevBbWidth * 100;

        // Calculate band expansion/contraction rate
        $expansionRate = ($bbWidth - $prevBbWidth) / $prevBbWidth * 100;

        // Track Bollinger Band width trend over lookback period
        $widthTrend = [];
        $priceAction = [];
        $lowestLow = $currentCandle['low'];
        $highestHigh = $currentCandle['high'];

        for ($i = 1; $i <= $lookbackPeriod; $i++) {
            if (!isset($data[$currentIndex - $i])) {
                continue;
            }

            $pastCandle = $data[$currentIndex - $i];
            $pastWidth = $pastCandle['bb_upper'] - $pastCandle['bb_lower'];
            $widthTrend[] = $pastWidth;
            $priceAction[] = $pastCandle['close'];

            if ($pastCandle['low'] < $lowestLow) {
                $lowestLow = $pastCandle['low'];
            }

            if ($pastCandle['high'] > $highestHigh) {
                $highestHigh = $pastCandle['high'];
            }
        }

        // Calculate metrics for decision making
        $isExpanding = $expansionRate > 0;
        $isContracting = $expansionRate < 0;
        $isNearLowerBand = $percentB <= 20;
        $isNearUpperBand = $percentB >= 80;
        $wasPreviouslyNearLowerBand = $prevPercentB <= 20;
        $wasPreviouslyNearUpperBand = $prevPercentB >= 80;

        // Check for price crossing bands
        $crossedLowerBand = $prevCandle['close'] < $prevCandle['bb_lower'] && $currentCandle['close'] >= $currentCandle['bb_lower'];
        $crossedUpperBand = $prevCandle['close'] > $prevCandle['bb_upper'] && $currentCandle['close'] <= $currentCandle['bb_upper'];

        // Check for BB squeeze (contraction followed by expansion)
        $bbSqueeze = false;
        $squeezeCount = 0;
        $minWidth = INF;

        for ($i = 1; $i <= min(5, $lookbackPeriod); $i++) {
            if (!isset($data[$currentIndex - $i]) || !isset($data[$currentIndex - $i - 1])) {
                continue;
            }

            $bbw = $data[$currentIndex - $i]['bb_upper'] - $data[$currentIndex - $i]['bb_lower'];
            $prevBbw = $data[$currentIndex - $i - 1]['bb_upper'] - $data[$currentIndex - $i - 1]['bb_lower'];

            if ($bbw < $minWidth) {
                $minWidth = $bbw;
            }

            if ($bbw < $prevBbw) {
                $squeezeCount++;
            }
        }

        $bbSqueeze = $squeezeCount >= 3 && $isExpanding && $minWidth > 0 && $bbWidth / $minWidth > 1.05;

        // Calculate trend momentum
        $downwardMomentum = 0;
        $upwardMomentum = 0;

        for ($i = 1; $i <= min(3, $lookbackPeriod); $i++) {
            if (!isset($data[$currentIndex - $i + 1]) || !isset($data[$currentIndex - $i])) {
                continue;
            }

            $candle = $data[$currentIndex - $i + 1];
            $prevCandle = $data[$currentIndex - $i];

            if ($candle['close'] < $prevCandle['close']) {
                $downwardMomentum++;
            } elseif ($candle['close'] > $prevCandle['close']) {
                $upwardMomentum++;
            }
        }

        // Calculate swing probabilities
        $longProbability = 0;
        $shortProbability = 0;
        $signal = 'neutral';
        $explanation = '';

        // Long probability calculation (looking for bottoms)
        if ($isNearLowerBand) {
            $longProbability += 25; // Price near lower band is bullish
            $explanation .= "Price near lower band (+25% long). ";
        }

        if ($crossedLowerBand) {
            $longProbability += 15; // Price crossing back inside from below the lower band
            $explanation .= "Price crossed above lower band (+15% long). ";
        }

        if ($isContracting && $downwardMomentum >= 2) {
            $longProbability += 15; // BB contracting after downtrend suggests consolidation
            $explanation .= "BB contracting after downtrend (+15% long). ";
        }

        if ($bbSqueeze && $currentCandle['close'] > $currentCandle['open']) {
            $longProbability += 20; // BB squeeze with bullish candle
            $explanation .= "BB squeeze with bullish candle (+20% long). ";
        }

        if ($percentB < $prevPercentB && $percentB < 20) {
            $longProbability -= 15; // Still moving towards lower band
            $explanation .= "Still moving downward (-15% long). ";
        }

        if ($currentCandle['close'] < $currentCandle['bb_lower']) {
            $longProbability -= 10; // Price below lower band suggests more downside possible
            $explanation .= "Price below lower band (-10% long). ";
        }

        if ($downwardMomentum == 3) {
            $longProbability -= 5; // Strong downward momentum
            $explanation .= "Strong downward momentum (-5% long). ";
        }

        // Price is finding support at BB lower and showing signs of reversal
        if ($isNearLowerBand && $currentCandle['close'] > $currentCandle['open'] && $prevCandle['close'] < $prevCandle['open']) {
            $longProbability += 20; // Bullish reversal candle after bearish candle near lower band
            $explanation .= "Bullish reversal pattern near lower band (+20% long). ";
        }

        // Short probability calculation (looking for tops)
        if ($isNearUpperBand) {
            $shortProbability += 25; // Price near upper band is bearish for continuation
            $explanation .= "Price near upper band (+25% short). ";
        }

        if ($crossedUpperBand) {
            $shortProbability += 15; // Price crossing back inside from above the upper band
            $explanation .= "Price crossed below upper band (+15% short). ";
        }

        if ($isContracting && $upwardMomentum >= 2) {
            $shortProbability += 15; // BB contracting after uptrend suggests consolidation
            $explanation .= "BB contracting after uptrend (+15% short). ";
        }

        if ($bbSqueeze && $currentCandle['close'] < $currentCandle['open']) {
            $shortProbability += 20; // BB squeeze with bearish candle
            $explanation .= "BB squeeze with bearish candle (+20% short). ";
        }

        if ($percentB > $prevPercentB && $percentB > 80) {
            $shortProbability -= 15; // Still moving towards upper band
            $explanation .= "Still moving upward (-15% short). ";
        }

        if ($currentCandle['close'] > $currentCandle['bb_upper']) {
            $shortProbability -= 10; // Price above upper band suggests more upside possible
            $explanation .= "Price above upper band (-10% short). ";
        }

        if ($upwardMomentum == 3) {
            $shortProbability -= 5; // Strong upward momentum
            $explanation .= "Strong upward momentum (-5% short). ";
        }

        // Price is finding resistance at BB upper and showing signs of reversal
        if ($isNearUpperBand && $currentCandle['close'] < $currentCandle['open'] && $prevCandle['close'] > $prevCandle['open']) {
            $shortProbability += 20; // Bearish reversal candle after bullish candle near upper band
            $explanation .= "Bearish reversal pattern near upper band (+20% short). ";
        }

        // Analyze the width trend for pattern recognition
        $widthExpandingCount = 0;
        $widthContractingCount = 0;

        for ($i = 1; $i < count($widthTrend); $i++) {
            if ($widthTrend[$i] > $widthTrend[$i - 1]) {
                $widthExpandingCount++;
            } elseif ($widthTrend[$i] < $widthTrend[$i - 1]) {
                $widthContractingCount++;
            }
        }

        // If bands were expanding but now contracting - potential reversal
        if ($widthExpandingCount >= 3 && $isContracting) {
            if ($isNearLowerBand) {
                $longProbability += 10;
                $explanation .= "Bands were expanding but now contracting near lower band (+10% long). ";
            } elseif ($isNearUpperBand) {
                $shortProbability += 10;
                $explanation .= "Bands were expanding but now contracting near upper band (+10% short). ";
            }
        }

        // Check for extreme readings that might suggest reversion
        if ($percentB <= 5) {
            $longProbability += 15; // Extremely oversold
            $explanation .= "Extremely oversold based on %B value (+15% long). ";
        } elseif ($percentB >= 95) {
            $shortProbability += 15; // Extremely overbought
            $explanation .= "Extremely overbought based on %B value (+15% short). ";
        }

        // Determine final signal based on probabilities
        if ($longProbability >= 50 && $longProbability > $shortProbability + 20) {
            $signal = 'long';
        } elseif ($shortProbability >= 50 && $shortProbability > $longProbability + 20) {
            $signal = 'short';
        } else {
            $signal = 'neutral';
        }

        // Cap probabilities at 100%
        $longProbability = min(100, max(0, $longProbability));
        $shortProbability = min(100, max(0, $shortProbability));

        return [
            'signal' => $signal,
            'long_probability' => $longProbability,
            'short_probability' => $shortProbability,
            'bb_width' => $bbWidth,
            'bb_width_change' => $expansionRate,
            'percent_b' => $percentB,
            'is_expanding' => $isExpanding,
            'is_contracting' => $isContracting,
            'bb_squeeze' => $bbSqueeze,
            'bb_upper_percent_change' => self::getPercentDiff($data[$currentIndex - 1]['bb_upper'], $data[$currentIndex]['bb_upper'], true),
            'bb_middle_percent_change' => self::getPercentDiff($data[$currentIndex - 1]['bb_middle'], $data[$currentIndex]['bb_middle'], true),
            'bb_lower_percent_change' => self::getPercentDiff($data[$currentIndex - 1]['bb_lower'], $data[$currentIndex]['bb_lower'], true),
            'message' => $explanation,
            'price_action' => [
                'upward_momentum' => $upwardMomentum,
                'downward_momentum' => $downwardMomentum,
                'is_near_lower_band' => $isNearLowerBand,
                'is_near_upper_band' => $isNearUpperBand,
                'crossed_lower_band' => $crossedLowerBand,
                'crossed_upper_band' => $crossedUpperBand
            ]
        ];
    }
    /**
     * Detect market trend using multiple technical indicators
     * 
     * @param array $data The complete candle data array
     * @param int $index Current candle index to analyze
     * @param float $threshold Percentage strength required to confirm a trend (default: 60%)
     * @param int $lookback Number of candles to consider for trend confirmation (default: 3)
     * @return array Returns trend information with direction and strength
     */

    public static function detectTrend(array $data, int $index, float $threshold = 60.0, int $lookback = 3): array
    {
        // Make sure we have enough data
        if ($index < $lookback) {
            return [
                'trend' => 'NEUTRAL',
                'strength' => 0,
                'message' => 'Not enough historical data to determine trend',
                'signals' => []
            ];
        }

        $candle = $data[$index];
        $signals = [];
        $bullishSignals = 0;
        $bearishSignals = 0;
        $totalSignals = 0;

        // 1. Price above/below moving averages
        if ($candle['close'] > $candle['ma7']) {
            $signals['MA7'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['MA7'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        if ($candle['close'] > $candle['ma25']) {
            $signals['MA25'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['MA25'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 2. EMA Cross
        if ($candle['ema12'] > $candle['ema26']) {
            $signals['EMA_CROSS'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['EMA_CROSS'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 3. RSI indicator
        if ($candle['rsi6'] > 50) {
            $signals['RSI'] = 'BULLISH';
            $bullishSignals++;
        } elseif ($candle['rsi6'] < 50) {
            $signals['RSI'] = 'BEARISH';
            $bearishSignals++;
        } else {
            $signals['RSI'] = 'NEUTRAL';
        }
        $totalSignals++;

        // 4. MACD signal
        if ($candle['histogram'] > 0) {
            $signals['MACD'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['MACD'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 5. Bollinger Bands position
        if ($candle['close'] > $candle['bb_middle']) {
            $signals['BB'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['BB'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 6. SAR indicator
        if ($candle['close'] > $candle['sar']) {
            $signals['SAR'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['SAR'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 7. ADX and Directional Movement
        if ($candle['adx'] > 25) { // Strong trend
            if ($candle['di_plus'] > $candle['di_minus']) {
                $signals['ADX'] = 'BULLISH';
                $bullishSignals++;
            } else {
                $signals['ADX'] = 'BEARISH';
                $bearishSignals++;
            }
        } else {
            $signals['ADX'] = 'NEUTRAL'; // Weak trend
        }
        $totalSignals++;

        // 8. Stochastic indicators
        if ($candle['stoch_k'] > $candle['stoch_d']) {
            $signals['STOCH'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['STOCH'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 9. Williams %R
        if ($candle['wr'] > -50) {
            $signals['WR'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['WR'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // 10. Check price action for lookback period
        $priceRising = true;
        $priceFalling = true;

        for ($i = $index - $lookback + 1; $i <= $index; $i++) {
            if ($i > 0) {
                if ($data[$i]['close'] <= $data[$i - 1]['close']) {
                    $priceRising = false;
                }
                if ($data[$i]['close'] >= $data[$i - 1]['close']) {
                    $priceFalling = false;
                }
            }
        }

        if ($priceRising) {
            $signals['PRICE_ACTION'] = 'BULLISH';
            $bullishSignals++;
        } elseif ($priceFalling) {
            $signals['PRICE_ACTION'] = 'BEARISH';
            $bearishSignals++;
        } else {
            $signals['PRICE_ACTION'] = 'NEUTRAL';
        }
        $totalSignals++;

        // 11. Volume trend
        if ($candle['volume'] > $candle['volumeMA5']) {
            if ($candle['close'] > $data[$index - 1]['close']) {
                $signals['VOLUME'] = 'BULLISH'; // Rising volume with price increase
                $bullishSignals++;
            } else {
                $signals['VOLUME'] = 'BEARISH'; // Rising volume with price decrease
                $bearishSignals++;
            }
        } else {
            $signals['VOLUME'] = 'NEUTRAL';
        }
        $totalSignals++;

        // 12. OBV and CVD trend
        if ($candle['obv'] > 0) {
            $signals['OBV'] = 'BULLISH';
            $bullishSignals++;
        } else {
            $signals['OBV'] = 'BEARISH';
            $bearishSignals++;
        }
        $totalSignals++;

        // Calculate trend strength as a percentage
        $bullStrength = ($bullishSignals / $totalSignals) * 100;
        $bearStrength = ($bearishSignals / $totalSignals) * 100;

        // Determine overall trend
        $trend = 'NEUTRAL';
        $strength = 0;
        $message = '';

        if ($bullStrength >= $threshold) {
            $trend = 'BULLISH';
            $strength = $bullStrength;
            $message = "Strong bullish trend detected with {$bullStrength}% confidence";
        } elseif ($bearStrength >= $threshold) {
            $trend = 'BEARISH';
            $strength = $bearStrength;
            $message = "Strong bearish trend detected with {$bearStrength}% confidence";
        } else {
            $trend = 'NEUTRAL';
            $strength = max($bullStrength, $bearStrength);
            $message = "No clear trend detected (Bull: {$bullStrength}%, Bear: {$bearStrength}%)";
        }

        return [
            'trend' => $trend,
            'strength' => $strength,
            'message' => $message,
            'signals' => $signals,
            'bullish_count' => $bullishSignals,
            'bearish_count' => $bearishSignals,
            'total_signals' => $totalSignals
        ];
    }




    public static function getCandleWick($candle, $type = 'upper')
    {
        if ($type === 'upper') {
            return $candle['high'] - max($candle['open'], $candle['close']);
        }

        if ($type === 'lower') {
            return   min($candle['open'], $candle['close']) - $candle['low'];
        }

        return null;
    }

    public static function getCandleSolidRegion($candle)
    {
        return abs($candle['open'] - $candle['close']);
    }















    // ######################### Functions for Time-based internal trader ##########################


    public static function dumpCoinData($coins, $interval, $timestamp = null)
    {

        $market = 'FUTURE';
        foreach ($coins as $coin) {

            $symbol = $coin->symbol;

            $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000, $timestamp, $market);
            $coinData = array_map(function ($candle) use ($symbol, $interval, $market) {
                return [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'market' => $market,
                    'timestamp' => $candle['binance_timestamp'],
                    'data' => json_encode($candle),
                ];
            }, $data);

            DB::table('candles')->insert($coinData);
        }

        return true;
    }




    // ################## Optimized Query for Data fetching ###########################

    public static function getCoinDataFromDb($symbol, $interval, $market, $startTime = null, $endTime = null, $batchSize = 5000)
    {
        // 1. Create a query builder but don't execute it yet
        $query = DB::table('candles')
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('market', $market);

        // 2. Add time constraints if provided
        if ($startTime) {
            $query->where('timestamp', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('timestamp', '<=', $endTime);
        }

        // 3. Add proper indexing hint if your DB supports it (MySQL example)
        // This assumes you have a composite index on (symbol, interval, market, timestamp)
        // $query->useIndex('idx_symbol_interval_market_timestamp');

        // 4. Use cursor for memory efficiency with large datasets
        $results = [];
        $query->orderBy('timestamp') // Ensure ordered results
            ->select('data')       // Only select the data column we need
            ->chunk($batchSize, function ($candles) use (&$results) {
                foreach ($candles as $candle) {
                    // Parse JSON directly into results array
                    $results[] = json_decode($candle->data, true);
                }
            });

        return $results;
    }

    /**
     * Alternative implementation using cursor() for even better memory efficiency
     * (Available in Laravel 5.3+)
     */
    public static function getCoinDataFromDbCursor($symbol, $interval, $market, $startTime = null, $endTime = null)
    {
        $query = DB::table('candles')
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('market', $market);

        if ($startTime) {
            $query->where('timestamp', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('timestamp', '<=', $endTime);
        }

        $results = [];

        // Use cursor() to avoid loading all records into memory at once
        foreach ($query->orderBy('timestamp')->select('data')->cursor() as $candle) {
            $results[] = json_decode($candle->data, true);
        }

        return $results;
    }

    /**
     * Cached version for repeated access to the same data
     */
    public static function getCoinDataFromDbCached($symbol, $interval, $market, $startTime = null, $endTime = null, $cacheTtl = 300)
    {
        // Create a unique cache key based on parameters
        $cacheKey = "coin_data:{$symbol}:{$interval}:{$market}:" .
            ($startTime ?? 'null') . ":" .
            ($endTime ?? 'null');

        // Try to get from cache first
        return Cache::remember($cacheKey, $cacheTtl, function () use ($symbol, $interval, $market, $startTime, $endTime) {
            $query = DB::table('candles')
                ->where('symbol', $symbol)
                ->where('interval', $interval)
                ->where('market', $market);

            if ($startTime) {
                $query->where('timestamp', '>=', $startTime);
            }

            if ($endTime) {
                $query->where('timestamp', '<=', $endTime);
            }

            $results = [];
            foreach ($query->orderBy('timestamp')->select('data')->cursor() as $candle) {
                $results[] = json_decode($candle->data, true);
            }

            return $results;
        });
    }




    // ################## ########################### ###########################
    public static function getUniqueTimestampsFromDb($interval, $startTime = null, $endTime = null)
    {
        $query = DB::table('candles')->where('interval', $interval);

        if ($startTime) {
            $query->where('timestamp', '>=', $startTime);
        }

        if ($endTime) {
            $query->where('timestamp', '<=', $endTime);
        }

        // Get timestamps, remove duplicates, sort, and return as array
        $timestamps = $query->pluck('timestamp')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $timestamps;
    }


    // Trade Operations
    public static function openInternalTrade($data)
    {

        DB::table('coin_reports')->insert($data);
    }
    public static function closeInternalTrade($id, $data)
    {
        DB::table('coin_reports')->where('id', $id)->update($data);
    }
    public static function checkOpenTradeInternal($symbol, $interval, $position, $formula)
    {
        return DB::table('coin_reports')
            ->where('symbol', $symbol)
            ->where('position', $position)
            ->where('interval', $interval)
            ->where('formula', $formula)
            ->whereNull('sellingCandle')
            ->first();
    }


    public static function analyzeTradeReport(array $trades): array
    {
        $profitable = [];
        $losses = [];
        $indicatorStats = [];

        foreach ($trades as $trade) {
            $buyingCandle = json_decode($trade['buyingCandle'], true);
            $isProfitable = $trade['profit'] > 0;

            if (!$buyingCandle || !isset($buyingCandle['timestamp'])) continue;

            // Define key indicators to analyze
            $indicators = [
                'rsi6',
                'mfi',
                'stoch_rsi',
                'stoch_k',
                'stoch_d',
                'wr',
                'adx',
                'di_plus',
                'di_minus',
                'histogram',
                'volume',
                'volumeMA5',
                'volumeMA10',
                'obv',
                'cvd',
                'vwap',
                'bb_upper',
                'bb_lower',
                'sar',
                'ema12',
                'ema26'
            ];

            $entry = ['symbol' => $trade['symbol'], 'timestamp' => $buyingCandle['timestamp']];
            foreach ($indicators as $key) {
                $entry[$key] = $buyingCandle[$key] ?? null;
            }

            if ($isProfitable) {
                $profitable[] = $entry;
            } else {
                $losses[] = $entry;
            }
        }

        // Helper: get avg and std dev per indicator
        $calculateStats = function ($trades, $indicators) {
            $stats = [];
            foreach ($indicators as $key) {
                $values = array_column($trades, $key);
                $values = array_filter($values, 'is_numeric');
                if (count($values) > 1) {
                    $avg = array_sum($values) / count($values);
                    $std = sqrt(array_sum(array_map(fn($v) => pow($v - $avg, 2), $values)) / count($values));
                    $stats[$key] = ['avg' => $avg, 'std_dev' => $std, 'count' => count($values)];
                }
            }
            return $stats;
        };

        $profitableStats = $calculateStats($profitable, $indicators);
        $lossStats = $calculateStats($losses, $indicators);

        // Comparison & Suggestions
        $suggestions = [];
        foreach ($indicators as $key) {
            if (!isset($profitableStats[$key], $lossStats[$key])) continue;

            $pAvg = $profitableStats[$key]['avg'];
            $lAvg = $lossStats[$key]['avg'];
            $difference = $pAvg - $lAvg;

            if (abs($difference) > 5) { // Adjust threshold based on indicator
                $suggestions[] = [
                    'indicator' => $key,
                    'profitable_avg' => round($pAvg, 2),
                    'loss_avg' => round($lAvg, 2),
                    'difference' => round($difference, 2),
                    'suggestion' => $difference > 0
                        ? "Avoid trades with low $key (avg in losses: $lAvg)"
                        : "Avoid trades with high $key (avg in losses: $lAvg)"
                ];
            }
        }

        return [
            'total_trades' => count($trades),
            'profitable_trades' => count($profitable),
            'loss_trades' => count($losses),
            'indicator_comparisons' => $suggestions,
            'profitable_stats' => $profitableStats,
            'loss_stats' => $lossStats,
        ];
    }

    public static function insertMasterControlEntry($key, $value)
    {
        return DB::table('master_control')->insert(
            [
                'key' => $key,
                'value' => $value,
            ]
        );
    }
    public static function getMasterControlEntry($key, $default = null)
    {
        $entry = DB::table('master_control')->where('key', $key)->first();
        return $entry ? $entry->value : $default;
    }
    public static function deleteMasterControlEntry($key)
    {
        $entry = DB::table('master_control')->where('key', $key)->delete();
        return true;
    }

    public static function updateMasterControlEntry($key, $newValue)
    {
        $entry = DB::table('master_control')->where('key', $key)->first();
        if ($entry) {
            DB::table('master_control')->where('key', $key)->update([
                'value' => $newValue,
            ]);
        } else {
            self::insertMasterControlEntry($key, $newValue);
        }
        return true;
    }



    public static function calculateIndicatorRatio($candleValue, $indicatorValue, $roundValue = 4)
    {
        return round(
            $indicatorValue / max($candleValue, 0.0001),
            $roundValue,
        );
    }













    // Live trader helpers

    public static function initiateLiveTradeSession($account)
    {
        DB::table('account_trade_details')->insert([
            'account' => $account,
            'spotWalletInitial' => json_encode(BinanceApiService::fetchSpotWalletDetails($account)),
            'futureWalletInitial' => json_encode(BinanceApiService::fetchFutureWalletDetails($account)),
            'spotWalletCurrent' => json_encode(BinanceApiService::fetchSpotWalletDetails($account)),
            'futureWalletCurrent' => json_encode(BinanceApiService::fetchFutureWalletDetails($account)),
            'totalTrades' => 0,
            'openTrades' => 0,
            'realizedPnl' => 0,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }


    public static function getLiveTradeSessionId($account)
    {
        $entry = DB::table('account_trade_details')->where('account',$account)->orderBy('created_at', 'DESC')->first();
        return $entry ? $entry->id : null;
    }

    public static function updateLiveTradeSession($account)
    {

        $id = self::getLiveTradeSessionId($account);


        $lastEntry =  DB::table('account_trade_details')->where('id', $id)->first();

        $spotWalletDetailsInitial = json_decode($lastEntry->spotWalletInitial, true);
        $futureWalletDetailsInitial =  json_decode($lastEntry->futureWalletInitial, true);

        $spotWalletDetailsCurrent = BinanceApiService::fetchSpotWalletDetails($account);
        $futureWalletCurrent = BinanceApiService::fetchFutureWalletDetails($account);



        $initialUsdt = 0;
        $finalUsdt = 0;

        // ===================Final Balance Calculation===================
        foreach ($spotWalletDetailsCurrent['total_assets'] as $assets) {
            if ($assets['asset'] === 'USDT') {
                $finalUsdt = $assets['free'];
            }
        }
        $finalUsdt += $futureWalletCurrent['wallet_balance'];
        // ================================================================


        // ===================Initial Balance Calculation===================
        foreach ($spotWalletDetailsInitial['total_assets'] as $assets) {
            if ($assets['asset'] === 'USDT') {
                $initialUsdt = $assets['free'];
            }
        }
        $initialUsdt += $futureWalletDetailsInitial['wallet_balance'];
        // ================================================================


        $totalTradesSpot = DB::table('live_trades_spot_results')->where('trade_acc', $account)->where('created_at', '>=', $lastEntry->created_at)->where('type', 'open')->count();
        $totalTradesFuture = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('created_at', '>=', $lastEntry->created_at)->where('type', 'open')->count();

        $openTradesSpot = DB::table('live_trades_spot_results')->where('trade_acc', $account)->where('created_at', '>=', $lastEntry->created_at)->where('trade_status', 'open')->count();
        $openTradesFuture = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('created_at', '>=', $lastEntry->created_at)->where('trade_status', 'open')->count();

        DB::table('account_trade_details')->where('id', $id)->update([
            'spotWalletCurrent' => json_encode($spotWalletDetailsCurrent),
            'futureWalletCurrent' => json_encode($futureWalletCurrent),
            'totalTrades' => $totalTradesFuture + $totalTradesSpot,
            'openTrades' => $openTradesSpot + $openTradesFuture,
            'realizedPnl' => $finalUsdt - $initialUsdt,
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }



  public static function roundToMatchPrecision($reference, $numberToRound) {
    // Get the decimal part of the reference number
    $decimalPlaces = 0;
    if (strpos((string)$reference, '.') !== false) {
        $decimalPlaces = strlen(substr(strrchr((string)$reference, "."), 1));
    }

    return round($numberToRound, $decimalPlaces);
}


}
