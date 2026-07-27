<?php
// Support Resistance formula (80 Trades with 87% accuracy) 1.5 SL
namespace App;

use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\HyperLiquidApiService;
use App\Services\MailerService;
use App\Services\OpeningConditionServiceLive;
use App\Services\SupervisorService;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DateTime;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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
    public static $backtestingTimestamps = [

        '1746126000000',
        '1740728740000',
        '1744830000000',
        '1748504740000',
        '1732561200000',
        '1744225200000',
        '1738136740000',
        '1722152740000',
        '1719819940000',
        '1725176740000',
    ];
    public static  $monthsFull = [
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
        'september',
        'october',
        'november',
        'december',
    ];

    public static $months = [
        'january' => 1,
        'jan' => 1,
        'february' => 2,
        'feb' => 2,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'may' => 5,
        'june' => 6,
        'jun' => 6,
        'july' => 7,
        'jul' => 7,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
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

    public static $tpSlcolors = [
        'FVG' => [
            'tp' => 'rgba(0, 128, 255, 0.3)',   // blue
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red
        ],
        'DOUBLE_BREAKOUTS' => [
            'tp' => 'rgba(255, 165, 0, 0.3)',   // orange
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red
        ],
        'TRENDLINE' => [
            'tp' => 'rgba(128, 0, 128, 0.3)',   // purple
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red
        ],
        'AGGRESSIVE' => [
            'tp' => 'rgba(0, 200, 150, 0.3)',   // teal
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red
        ],
        'ORDERBLOCK' => [
            'tp' => 'rgba(255, 215, 0, 0.3)',   // gold
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red
        ],
        'DEFAULT' => [
            'tp' => 'rgba(0, 255, 0, 0.3)',     // green (original)
            'sl' => 'rgba(255, 0, 0, 0.3)',     // red (original)
        ],
    ];



    /**
     * Create a new class instance.
     */
    public function __construct() {}


    public static function getDayNameFromTimestamp($timestampMs, $timezone = 'GMT+0')
    {
        // Convert milliseconds → seconds
        $timestamp = intval($timestampMs / 1000);

        // Extract numeric offset (handles formats like GMT+5, GMT-3, GMT+05)
        $offset = str_replace('GMT', '', strtoupper(trim($timezone)));

        // Handle GMT+0 or GMT-0 explicitly
        if ($offset === '+0' || $offset === '-0' || $offset === '0' || $offset === '') {
            $tzString = 'Etc/GMT';
        } else {
            // Flip sign because of Etc/GMT convention
            if (strpos($offset, '+') === 0) {
                $offset = '-' . substr($offset, 1);
            } elseif (strpos($offset, '-') === 0) {
                $offset = '+' . substr($offset, 1);
            }
            $tzString = 'Etc/GMT' . $offset;
        }

        // Create Carbon instance
        $date = Carbon::createFromTimestamp($timestamp, $tzString);

        // Return lowercase 3-letter day name (mon, tue, wed...)
        return strtolower($date->format('D'));
    }

    public static function getSettingsValue($setting_key, $default)
    {
        return DB::table('trade_settings')->where('settings_key', $setting_key)->first()->settings_value ?? $default;
    }
    public static function getMetaValue($id, $meta_key, $default)
    {
        return DB::table('user_meta')->where('user_id', $id)->where('meta_key', $meta_key)->first()->meta_value ?? $default;
    }

    public static  function console_log($message, $type = 'info')
    {
        // Only log in console context
        if (php_sapi_name() === 'cli') {
            $prefix = match ($type) {
                'error' => "\033[31m[ERROR]\033[0m ", // red
                'warn', 'warning' => "\033[33m[WARN]\033[0m ", // yellow
                'success' => "\033[32m[SUCCESS]\033[0m ", // green
                default => "\033[36m[INFO]\033[0m ", // cyan
            };
            echo $prefix . (is_array($message) || is_object($message)
                ? json_encode($message, JSON_PRETTY_PRINT)
                : $message) . PHP_EOL;
        }
    }

    public static function getDataParamsFromMonth($month, $year, $interval)
    {

        $month = CommonHelpers::$months[$month];
        $calander = CommonHelpers::generateCalendar($year, $month);
        $limit = CommonHelpers::getIndexDiffFromTimestamps(
            $calander['months'][0]['startTime'],
            $calander['months'][0]['endTime'],
            $interval
        ) + 1;
        $timestamp = $calander['months'][0]['startTime'];


        return [
            'timestamp' => $timestamp,
            'limit' => $limit
        ];
    }

    public static function generateCalendar($year, $month = null)
    {
        $months = [];
        $weeks = [];

        // If month not given → generate whole year
        if ($month === null) {
            $startDate = new DateTime("$year-01-01 00:00:00");
            $endDate   = new DateTime("$year-12-31 23:59:59");
        } else {
            $startDate = new DateTime("$year-$month-01 00:00:00");
            $endDate   = (clone $startDate)->modify('last day of this month 23:59:59');
        }

        // -------- Monthly Calendar --------
        $interval = new DateInterval('P1M');
        $period   = new DatePeriod(clone $startDate, $interval, $endDate);

        foreach ($period as $dt) {
            $monthStart = (clone $dt)->modify('first day of this month 00:00:00');
            $monthEnd   = (clone $dt)->modify('last day of this month 23:59:59');

            $months[] = [
                'startTime' => $monthStart->getTimestamp() * 1000,
                'endTime'   => $monthEnd->getTimestamp() * 1000,
                'label'     => $monthStart->format('F Y'),
            ];
        }

        // -------- Weekly Calendar --------
        // Start from first Monday of given period
        $weekStart = clone $startDate;
        if ($weekStart->format('N') != 1) { // If not Monday, go back to Monday
            $weekStart->modify('last monday');
        }

        while ($weekStart <= $endDate) {
            $weekEnd = (clone $weekStart)->modify('sunday 23:59:59');

            // clip last week if it exceeds endDate
            if ($weekEnd > $endDate) {
                $weekEnd = clone $endDate;
            }

            $weeks[] = [
                'startTime' => $weekStart->getTimestamp() * 1000,
                'endTime'   => $weekEnd->getTimestamp() * 1000,
                'label'     => "Week of " . $weekStart->format('d M Y'),
            ];

            $weekStart->modify('+1 week');
        }

        return [
            'months' => $months,
            'weeks'  => $weeks,
        ];
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
    public static function checkOpenOrder($symbol = null, $interval, $market, $trade_acc)
    {

        $tableName = $market === 'FUTURE' ? 'live_trades_future_results' : 'live_trades_spot_results';
        $open_orders =  DB::table($tableName);
        if ($symbol) {
            $open_orders->where('symbol', $symbol);
        }
        $open_orders->where('position', $interval)
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



    public static function getSMAAtIndex(array $data, int $index, int $period, string $key = 'volume'): ?float
    {
        if ($index + 1 < $period) {
            return null; // Not enough data before this index
        }

        $start = max(0, $index - $period + 1);
        $slice = array_slice($data, $start, $period);

        $sum = 0;
        foreach ($slice as $item) {
            if (!isset($item[$key])) {
                return null; // Invalid data
            }
            $sum += $item[$key];
        }

        return $sum / $period;
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

    public static function checkWorkerEngagement($workerId, $symbol, $account)
    {
        return DB::table('worker_symbols')->where('symbol', $symbol)->where('worker_id', '!=', $workerId)->first();
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


    public static function filterCandlestickData(array $data, ?int $startTimestamp, ?int $endTimestamp): array
    {
        $filtered = array_filter($data, function ($candle) use ($startTimestamp, $endTimestamp) {
            if (!isset($candle['binance_timestamp'])) {
                return false;
            }

            $ts = $candle['binance_timestamp'];

            if ($startTimestamp !== null && $ts < $startTimestamp) {
                return false;
            }
            if ($endTimestamp !== null && $ts > $endTimestamp) {
                return false;
            }

            return true;
        });

        // reindex to 0..n
        return array_values($filtered);
    }

    public static function createOrUpdateZone($symbol, $interval, $type, $top, $bottom, $timestampInitial, $timestampConfirmed = null, $status = 'active', $name = 'default')
    {
        // Check if zone already exists for this symbol + timestamp
        $existingZone = DB::table('sd_zones')
            ->where('timestamp_initial', $timestampInitial)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->first();

        if ($existingZone) {
            // Update existing zone
            DB::table('sd_zones')
                ->where('id', $existingZone->id)
                ->update([
                    'type' => $type,
                    'top' => $top,
                    'bottom' => $bottom,
                    'timestamp_confirmed' => $timestampConfirmed,
                    'status' => $status,
                ]);

            return $existingZone->id;
        }

        // Insert new zone
        return DB::table('sd_zones')->insertGetId([
            'symbol' => $symbol,
            'interval' => $interval,
            'type' => $type,
            'top' => $top,
            'bottom' => $bottom,
            'name' => $name,
            'timestamp_initial' => $timestampInitial,
            'timestamp_confirmed' => $timestampConfirmed,
            'status' => $status,
        ]);
    }

    /**
     * Calculate or validate the Risk-to-Reward (R:R) ratio for a trade.
     *
     * Formula:
     *   R:R = |Entry - TP| ÷ |Entry - SL|
     *
     * Example:
     *   Entry = 100, TP = 110, SL = 95
     *   R:R = (110 - 100) / (100 - 95) = 10 / 5 = 2.0
     *
     * Notes:
     * - If Entry == SL → division by zero risk. Returns INF (infinite) if TP != Entry,
     *   or 0 if TP == Entry (no reward).
     *
     * @param float      $entry     The trade entry price.
     * @param float      $tp        The target (take-profit) price.
     * @param float      $sl        The stop-loss price.
     * @param float|null $threshold Optional. If provided, returns a boolean
     *                              indicating whether R:R meets/exceeds the threshold.
     *
     * @return float|bool The calculated R:R ratio (if $threshold is null),
     *                    or a boolean result (true/false) if $threshold is set.
     */
    public static function checkRR(float $entry, float $tp, float $sl, float $threshold = null): float|bool
    {
        $risk = abs($entry - $sl);
        $reward = abs($entry - $tp);

        // 🛑 Handle zero-risk (entry == SL)
        if ($risk == 0.0) {
            // If TP also equals entry → no reward, return 0
            $ratio = ($reward == 0.0) ? 0.0 : INF;
        } else {
            $ratio = $reward / $risk;
        }

        // If threshold provided → return boolean validation
        if ($threshold !== null) {
            return $ratio >= $threshold;
        }

        // Otherwise → return numeric R:R value
        return $ratio;
    }


    public static function getZoneByTimestampAndType($symbol, $timestampInitial)
    {
        return DB::table('sd_zones')
            ->where('timestamp_initial', $timestampInitial)
            ->where('symbol', $symbol)
            ->first();
    }
    public static function addZoneActivity($zoneId, $activity, $timestamp, $action)
    {
        return DB::table('sd_zones_activities')->insertGetId([
            'zone_id' => $zoneId,
            'activity' => $activity,
            'timestamp' => $timestamp,
            'action' => $action,
        ]);
    }
    public static function flushZones($symbol = null)
    {


        DB::table('sd_zones')->truncate();
        DB::table('sd_zones_activities')->truncate();
    }



    /**
     * Generate a label/marker plot for chart annotations.
     *
     * @param int    $timestamp   Base timestamp (Unix ms)
     * @param string $color       Label color (default 'orange')
     * @param string $text        Label text
     * @param string $position    Label position: 'aboveBar' | 'belowBar'
     * @param string $timezone    Timezone for adjustment ('pst' or 'utc'), default = 'pst'
     *
     * @return array
     */
    public static function generateLabelPlot(
        int $timestamp,
        string $color = 'orange',
        string $text = '',
        string $position = 'aboveBar',
        string $timezone = 'binance'
    ) {
        // timezone adjustment: PST offset (in ms) = 5 hours = 18,000,000 ms
        $tsAdjustment = $timezone === 'binance' ? 18000000 : 0;

        return [
            'timestamp_pst' => $timestamp + $tsAdjustment,
            'color'         => $color,
            'text'          => $text,
            'position'      => $position
        ];
    }

    /**
     * Generate a structured trade plot array for charting.
     *
     * @param string $position   Trade direction: 'LONG' or 'SHORT'
     * @param int    $startTime  Trade entry timestamp (Unix ms)
     * @param int    $endTime    Trade exit timestamp (Unix ms)
     * @param float  $entryPrice Entry price of trade
     * @param float  $tp         Take profit level
     * @param float  $sl         Stop loss level
     * @param float  $profit     Profit/loss value for trade
     * @param string $timezone   Timezone for adjustment ('pst' or 'utc'), default = 'pst'
     *
     * @return array Formatted trade plot structure
     */
    public static function generateTradePlot(
        string $position,
        int $startTime,
        int $endTime,
        float $entryPrice,
        float $tp,
        float $sl,
        float $profit = 0,
        array $colors = [
            'tp' => 'rgba(0, 255, 0, 0.3)',
            'sl' => 'rgba(255, 0, 0, 0.3)'
        ],
        string $timezone = 'binance'
    ) {
        // timezone adjustment: PST offset (in ms) = 5 hours = 18,000,000 ms
        $tsAdjustment = $timezone === 'binance' ? 18000000 : 0;

        return [
            'type'          => strtoupper($position), // LONG / SHORT
            'startTimestamp' => $startTime + $tsAdjustment,
            'endTimestamp'  => $endTime + $tsAdjustment,
            'entryPrice'    => $entryPrice,
            'tp'            => $tp,
            'sl'            => $sl,
            'tpColor'       => $colors['tp'],   // green for TP
            'slColor'       => $colors['sl'],   // red for SL
            'profit'        => $profit,
        ];
    }

    public static function plotZones($activeZone, $supplyZone, $demandZone, $endTime, &$trades, $startTime = null)
    {
        $topLevel = $supplyZone;
        $bottomLevel = $demandZone;
        $activeLevel = $activeZone;
        if ($topLevel) {
            $trades[] = CommonHelpers::generateZonePlot(
                $topLevel['top'],
                $topLevel['bottom'],
                $startTime ?? $topLevel['timestamp_initial'],
                $endTime,
                'red',
                'binance'
            );
        }
        if ($bottomLevel) {

            $trades[] = CommonHelpers::generateZonePlot(
                $bottomLevel['top'],
                $bottomLevel['bottom'],
                $startTime ?? $bottomLevel['timestamp_initial'],
                $endTime,
                'green',
                'binance'
            );
        }
        if ($activeLevel) {

            $trades[] = CommonHelpers::generateZonePlot(
                $activeLevel['top'],
                $activeLevel['bottom'],
                $startTime ?? $activeLevel['timestamp_initial'],
                $endTime,

                'blue',
                'binance'
            );
        }
    }
    /**
     * Generate a Zone Plot array for chart visualization
     *
     * @param float  $top       The upper boundary of the zone (e.g., resistance level).
     * @param float  $bottom    The lower boundary of the zone (e.g., support level).
     * @param int    $startTime Start timestamp in milliseconds (Unix ms).
     * @param int    $endTime   End timestamp in milliseconds (Unix ms).
     * @param string $color     Zone highlight color name (yellow, blue, green, red, purple, orange). Default: yellow.
     * @param string $timezone  Timezone for adjustment ('pst' adds +5h offset). Default: 'pst'.
     *
     * @return array Zone plot data structure
     */
    public static function generateZonePlot(
        float $top,
        float $bottom,
        int $startTime,
        int $endTime,
        string $color = 'yellow',
        string $timezone = 'binance'
    ): array {
        // Apply timezone adjustment (PST = +5 hours in ms)
        $tsAdjustment = ($timezone === 'binance') ? 18000000 : 0;

        // Define color map with RGBA (opacity 0.3)
        $colorMap = [
            'yellow' => 'rgba(255, 255, 0, 0.2)',
            'blue'   => 'rgba(0, 0, 255, 0.2)',
            'green'  => 'rgba(0, 255, 0, 0.2)',
            'red'    => 'rgba(255, 0, 0, 0.2)',
            'purple' => 'rgba(128, 0, 128, 0.2)',
            'orange' => 'rgba(255, 165, 0, 0.2)',
        ];

        // Fallback to yellow if color not found
        $color = $colorMap[$color] ?? $colorMap['yellow'];

        return [
            'type'          => 'LONG',
            'startTimestamp' => $startTime + $tsAdjustment, // Unix ms
            'endTimestamp'  => $endTime +  $tsAdjustment,
            'entryPrice'    => $bottom,
            'tp'            => $top,
            'sl'            => $bottom,
            'tpColor'       => $color,
            'slColor'       => 'rgba(255, 0, 0, 0)', // invisible
            'markers'       => false,
            'profit'        => 0,
        ];
    }
    /**
     * Check if two price ranges intersect
     *
     * @param float $low       Lower bound of first range
     * @param float $high      Upper bound of first range
     * @param float $zoneLow   Lower bound of second range
     * @param float $zoneHigh  Upper bound of second range
     *
     * @return bool True if ranges intersect, false otherwise
     */
    public static function rangesIntersect(float $low, float $high, float $zoneLow, float $zoneHigh): bool
    {
        return max($low, $zoneLow) <= min($high, $zoneHigh);
    }
    /**
     * Generate a Line Plot array for chart visualization
     *
     * @param int    $startTime  Start timestamp in milliseconds (Unix ms).
     * @param float  $startValue Y-axis value (price) at the start timestamp.
     * @param int    $endTime    End timestamp in milliseconds (Unix ms).
     * @param float  $endValue   Y-axis value (price) at the end timestamp.
     * @param string $color      Line color name (yellow, blue, green, red, purple, orange, black). Default: yellow.
     * @param string $timezone   Timezone for adjustment ('pst' adds +5h offset). Default: 'pst'.
     * @param string|null $title Optional label/title for the line (e.g., "Trendline", "Support").
     * @param int    $thickness  Line thickness in pixels. Default: 2.
     *
     * @return array Line plot data structure for chart rendering
     */
    public static function generateLinePlot(
        int $startTime,
        float $startValue,
        int $endTime,
        float $endValue,
        string $color = 'yellow',
        string $timezone = 'binance',
        ?string $title = null,
        int $thickness = 2
    ): array {
        // Apply timezone adjustment (PST = +5 hours in ms)
        $tsAdjustment = $timezone === 'binance' ? 18000000 : 0;

        // Define supported colors (using solid hex for lines)
        $colorMap = [
            'yellow' => '#FFD700',
            'blue'   => '#0000FF',
            'green'  => '#00FF00',
            'red'    => '#FF0000',
            'purple' => '#800080',
            'orange' => '#FFA500',
            'black'  => '#000000',
        ];

        // Fallback to yellow if color not found
        $color = $colorMap[$color] ?? $colorMap['yellow'];

        return [
            'x1'        => $startTime + $tsAdjustment, // timestamp for first point
            'y1'        => $startValue,                // price for first point
            'x2'        => $endTime + $tsAdjustment,   // timestamp for second point
            'y2'        => $endValue,                  // price for second point
            'color'     => $color,                     // line color
            'thickness' => $thickness,                 // line thickness
            'title'     => $title ?? ''                // optional label
        ];
    }



    // FVG Calculation

    public static function getLatestFVGatIndex($data, $index, $fillMethod = 'body', float $gapThreshold = 0.6, $fillPercent = 50)
    {
        if ($index <= 10) {
            return null;
        }

        $loopIndex = $index - 1;
        $latestFVG = null;
        $maxLookback = 50;
        $startIndex = $index - $maxLookback;

        while ($loopIndex > $startIndex && $loopIndex > 10) {
            $fvg = null;

            // --- Detect Bullish FVG ---
            if (
                // $data[$loopIndex]['per'] > 0
                // && $data[$loopIndex + 1]['per'] > 0 
                // &&  $data[$loopIndex - 1]['per'] > 0 
                isset($data[$loopIndex - 1], $data[$loopIndex + 1])
            ) {
                $gapDistance = CommonHelpers::getPercentDiff(
                    $data[$loopIndex - 1]['high'],
                    $data[$loopIndex + 1]['low'],
                    true
                );

                if (
                    $gapDistance >= $gapThreshold
                ) {
                    $fvg = [
                        'type' => 'bullish',
                        'index' => $loopIndex,
                        'distance' => $gapDistance,
                        'top' => $data[$loopIndex + 1]['low'],
                        'midpoint' => ($data[$loopIndex + 1]['low'] + $data[$loopIndex - 1]['high']) / 2, // New field
                        'bottom' => $data[$loopIndex - 1]['high'],
                        'timestamp' => $data[$loopIndex]['binance_timestamp'],
                        'timestamp_pst' => $data[$loopIndex]['timestamp_pst'],
                        'timestampReadable' => $data[$loopIndex]['timestampReadable'],
                    ];
                }
            }
            // --- Detect Bearish FVG ---
            else if (
                // $data[$loopIndex - 1]['per'] < 0
                // && $data[$loopIndex + 1]['per'] < 0 
                // &&  $data[$loopIndex - 1]['per'] < 0 
                isset($data[$loopIndex - 1], $data[$loopIndex + 1])
            ) {
                $gapDistance = CommonHelpers::getPercentDiff(
                    $data[$loopIndex + 1]['high'],
                    $data[$loopIndex - 1]['low'],
                    true
                );

                if ($gapDistance >= $gapThreshold) {
                    $fvg = [
                        'type' => 'bearish',
                        'index' => $loopIndex,
                        'distance' => $gapDistance,
                        'midpoint' => ($data[$loopIndex - 1]['low'] + $data[$loopIndex + 1]['high']) / 2, // New field
                        'top' => $data[$loopIndex - 1]['low'],
                        'bottom' => $data[$loopIndex + 1]['high'],
                        'timestamp' => $data[$loopIndex]['binance_timestamp'],
                        'timestamp_pst' => $data[$loopIndex]['timestamp_pst'],
                        'timestampReadable' => $data[$loopIndex]['timestampReadable'],
                    ];
                }
            }

            // --- If FVG found, validate it ---
            if ($fvg) {
                $fvg['filledIndex'] = null;
                $fvg['filledMethod'] = null;
                $fvg['fillPercent'] = null;
                $isInvalidated = false;

                for ($i = $fvg['index'] + 1; $i <= $index; $i++) {
                    if (!isset($data[$i])) {
                        break;
                    }

                    $top = $fvg['top'];
                    $bottom = $fvg['bottom'];

                    // --- Bullish Check ---
                    if ($fvg['type'] === 'bullish') {
                        // invalidation: close below bottom
                        if ($data[$i]['low'] < $bottom) {
                            $isInvalidated = true;
                            break;
                        }

                        // fill check
                        $value = $fillMethod === 'wick' ? $data[$i]['low'] : min($data[$i]['close'], $data[$i]['open']);
                        $percent = 100 - (($value - $bottom) / ($top - $bottom) * 100);
                        if ($percent >= $fillPercent) {
                            $fvg['filledIndex'] = $i;
                            $fvg['filledMethod'] = $fillMethod;
                            $fvg['fillPercent'] = $percent;
                            break;
                        }
                    }
                    // --- Bearish Check ---
                    else if ($fvg['type'] === 'bearish') {
                        // invalidation: close above top
                        if ($data[$i]['high'] > $top) {
                            $isInvalidated = true;
                            break;
                        }

                        // fill check
                        $value = $fillMethod === 'wick' ? $data[$i]['high'] : max($data[$i]['close'], $data[$i]['open']);
                        $percent = (($value - $bottom) / ($top - $bottom) * 100);
                        if ($percent >= $fillPercent) {
                            $fvg['filledIndex'] = $i;
                            $fvg['filledMethod'] = $fillMethod;
                            $fvg['fillPercent'] = $percent;
                            break;
                        }
                    }
                }

                // --- Final decision ---
                if (!$isInvalidated && !$fvg['filledIndex']) {
                    $latestFVG = $fvg;
                    break; // stop at the first active, unfilled FVG
                }
            }

            $loopIndex--;
        }

        return $latestFVG;
    }






    public static function getLatestFibZone($data, $index, $type = 'bullish')
    {


        $loopIndex = $index - 3;
        $fibZone = null;
        $hPivotIndex = null;
        $lPivotIndex = null;

        if ($type === 'bullish') {
            while ($loopIndex > 10) {
                $pivot = CommonHelpers::checkPivot($data, $loopIndex, 3);


                if (!$hPivotIndex) {
                    if ($pivot === 'high_pivot') {
                        $hPivotIndex = $loopIndex;
                    }
                } else {
                    if ($pivot === 'low_pivot') {
                        $lPivotIndex = $loopIndex;
                        break;
                    }
                }
                $loopIndex--;
            }




            if ($lPivotIndex && $hPivotIndex) {


                $diff = $data[$hPivotIndex]['high'] - $data[$lPivotIndex]['low'];

                $zoneUpper = $data[$hPivotIndex]['high'] - ($diff * 0.5);   // 50% retracement
                $zoneLower = $data[$hPivotIndex]['high'] - ($diff * 0.618); // 61.8% retracement
                $fibZone = [
                    'start_index' => $lPivotIndex,
                    'type' => $type,
                    'l_pivot' => $lPivotIndex,
                    'h_pivot' => $hPivotIndex,
                    'l_value' => $data[$lPivotIndex]['low'],
                    'h_value' => $data[$hPivotIndex]['high'],
                    'upper' => $zoneUpper,
                    'lower' => $zoneLower,
                    'percent_gain' => CommonHelpers::getPercentDiff($data[$lPivotIndex]['low'], $data[$hPivotIndex]['high'], true),
                ];
            }
        }
        return $fibZone;
    }


    public static function getProgressionDetails($formula, $position, $binance_timestamp, $tagName = null)
    {


        $rawData = DB::table('coin_reports')
            ->selectRaw("
                    JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp')) as buying_timestamp,
                    symbol,
                    COUNT(*) as total_trades,
                    SUM(profit) as profit,
                    SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as profitable_trades,
                    SUM(CASE WHEN profit <= 0 THEN 1 ELSE 0 END) as loss_trades,
                    ROUND((SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as accuracy
                ")
            ->where('formula', $formula);


        if ($tagName) {
            $rawData->where('tagName', $tagName);
        }

        $rawData = $rawData->where('position', $position)
            ->whereNotNull('sellingCandle')
            ->whereRaw("JSON_EXTRACT(sellingCandle, '$.binance_timestamp') <= ?", [$binance_timestamp])
            ->groupBy(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp'))"),
                'symbol'
            )
            ->orderBy('buying_timestamp', 'ASC')
            ->get();
        $grouped = [];

        foreach ($rawData as $row) {
            $timestamp = $row->buying_timestamp;

            if (!isset($grouped[$timestamp])) {
                $grouped[$timestamp] = [
                    'timestamp' => $timestamp,
                    'total_profit' => 0,
                    'total_loss' => 0,
                    'profit' => 0,
                    'accuracy' => 0,
                    'high_accuracy_symbols' => [],
                ];
            }

            $grouped[$timestamp]['total_profit'] += $row->profitable_trades;
            $grouped[$timestamp]['total_loss'] += $row->loss_trades;
            $grouped[$timestamp]['profit'] += $row->profit;

            if ($row->accuracy > 90) {
                $grouped[$timestamp]['high_accuracy_symbols'][] = $row->symbol;
            }
        }

        // Now calculate overall accuracy per timestamp
        foreach ($grouped as &$item) {
            $totalTrades = $item['total_profit'] + $item['total_loss'];
            $item['accuracy'] = $totalTrades > 0
                ? round(($item['total_profit'] / $totalTrades) * 100, 2)
                : 0;
        }


        return $grouped;
    }




    public static function parseFrequency($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


        if (!$hours) {
            $filterHoursStartTime = 0;
        }


        $totalProfits = 0;
        $totalLosses = 0;

        foreach ($grouped as $timestamp => $data) {
            if ($timestamp <= $endTime && $timestamp >= $filterHoursStartTime) {
                $totalLosses += $data['total_loss'];
                $totalProfits += $data['total_profit'];
            }
        }



        $totalTrades = $totalProfits + $totalLosses;
        return $totalTrades != 0 ? ($totalProfits / $totalTrades) * 100 : -1;
    }




    public static function parseAccuracy($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


        if (!$hours) {
            $filterHoursStartTime = 0;
        }

        $totalProfits = 0;
        $totalLosses = 0;

        foreach ($grouped as $timestamp => $data) {
            if ($timestamp <= $endTime && $timestamp >= $filterHoursStartTime) {
                $totalLosses += $data['total_loss'];
                $totalProfits += $data['total_profit'];
            }
        }




        $totalTrades = $totalProfits + $totalLosses;
        return $totalTrades != 0 ? ($totalProfits / $totalTrades) * 100 : -1;
    }
    public static function parseProfit($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


        if (!$hours) {
            $filterHoursStartTime = 0;
        }

        $totalProfits = 0;
        $totalLosses = 0;
        $netProfit = 0;

        foreach ($grouped as $timestamp => $data) {
            if ($timestamp <= $endTime && $timestamp >= $filterHoursStartTime) {
                $totalLosses += $data['total_loss'];
                $totalProfits += $data['total_profit'];
                $netProfit += $data['profit'];
            }
        }




        return $netProfit;
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

        if (empty($data) || !isset($data[0][0])) {
            return [];
        }

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
        MailerService::sendSafetyAlert($log, false);
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


    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval, $rounded = true)
    {
        if (!($timestamp1 && $timestamp2)) {
            return false;
        }
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $diff = abs($timestamp1 - $timestamp2) / (60 * 1000 * $intervalToMins);
        return $rounded ? intval($diff) : $diff;
    }

    public static function findIndexFromTimestamp($data, $index, $timestamp, $interval = '15m')
    {

        return $index - OpeningConditionServiceLive::getIndexDiffFromTimestamps($timestamp, $data[$index]['binance_timestamp'], $interval);
    }
    public static function buildDateTime($hh, $min, $sec = null, $dd, $mm, $yyyy)
    {
        // If $sec not provided, default to 0
        $sec = $sec ?? 0;

        // Create a UNIX timestamp
        $timestamp = mktime($hh, $min, $sec, $mm, $dd, $yyyy);

        // Format as MySQL-style datetime string
        return date("Y-m-d H:i:s", $timestamp);
    }
    public static function compareCandlestickTime($hh, $min, $sec = null, $dd, $mm, $yyyy, $candle)
    {
        return $candle['timestampReadable'] === CommonHelpers::buildDateTime($hh, $min, $sec, $dd, $mm, $yyyy);
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
        return DB::table('coin_reports_safe_mode')->insertGetId($data);
    }
    public static function closeInternalTrade($id, $data)
    {
        DB::table('coin_reports_safe_mode')->where('id', $id)->update($data);
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
        $entry = DB::table('account_trade_details')->where('account', $account)->orderBy('created_at', 'DESC')->first();
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



    public static function roundToMatchPrecision($reference, $numberToRound)
    {
        // Get the decimal part of the reference number
        $decimalPlaces = 0;
        if (strpos((string)$reference, '.') !== false) {
            $decimalPlaces = strlen(substr(strrchr((string)$reference, "."), 1));
        }

        return round($numberToRound, $decimalPlaces);
    }


    // #################### Workers Management Functions ###################

    public static function updateWorkerTicker($workerId)
    {
        DB::table('workers')->where('worker_id', $workerId)->update([
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }


    public static function updateLiveTradesMasterTable($account)
    {


        $url = "https://" . $account->domain_name . "/master-process/handle/" . config('binance.process_manager_client_key');

        $data = [
            'action' => 'FETCH_LIVE_TRADES_FUTURE',
            'email' => $account->email
        ];
        $response = Http::post($url, $data);

        if ($response->successful()) {
            $response = $response->json();


            $trades = $response['data'];

            $orderIds = array_map(function ($trade) {
                return $trade['orderId'];
            }, $trades);
            $domain_names = array_map(function ($trade) use ($response) {
                return $response['domain'];
            }, $trades);

            $trade_accs = array_map(function ($trade) {
                return $trade['trade_acc'];
            }, $trades);

            // Delete Unwanted or closed trades
            DB::table('live_trades_master')
                ->whereNotIn('orderId', $orderIds)
                ->whereNotIn('domain_name', $domain_names)
                ->whereNotIn('trade_acc', $trade_accs)
                ->delete();

            // Dump New Trades
            foreach ($trades as $trade) {

                DB::table('live_trades_master')->updateOrInsert(
                    [
                        "orderId" => $trade['orderId'],
                        "trade_acc" => $trade['trade_acc'],
                        "domain_name" => $response['domain'],
                    ],
                    [
                        "id" => $trade['id'],
                        "market" => $trade['market'],
                        "symbol" => $trade['symbol'],
                        "side" => $trade['side'],
                        "position" => $trade['position'],
                        "type" => $trade['type'],
                        "amount" => $trade['amount'],
                        "previousPrice" => $trade['previousPrice'],
                        "trade_status" => $trade['trade_status'],
                        "stopLoss" => $trade['stopLoss'],
                        "stopLossReductionPrecentage" => $trade['stopLossReductionPrecentage'],
                        "qty" => $trade['qty'],
                        "leverage" => $trade['leverage'],
                        "liqPrice" => $trade['liqPrice'],
                        "price" => $trade['price'],
                        "created_at" => $trade['created_at'],
                        "updated_at" => $trade['updated_at'],
                        "pairId" => $trade['pairId'],
                        "currentPrice" => $trade['currentPrice'],
                        "currentSupport" => $trade['currentSupport'],
                        "currentResistance" => $trade['currentResistance'],
                        "currentProfit" => $trade['currentProfit'],
                        "targetProfit" => $trade['targetProfit'],
                        "formula" => $trade['formula'],
                        "isDummy" => $trade['isDummy'],
                        "realizedPnl" => $trade['realizedPnl'],
                        "feeUsdt" => $trade['feeUsdt'],
                        "turnoverPoint" => $trade['turnoverPoint'],
                        "exchange" => $trade['exchange'],
                        "worker_id" => $trade['worker_id'],
                        "last_trade_update_seconds" => $trade['last_trade_update_seconds'],
                        "last_worker_update_seconds" => $trade['last_worker_update_seconds'],
                        "user_email" => $trade['user_email'],
                        "tp_order_id" => $trade['tp_order_id'],
                        "sl_order_id" => $trade['sl_order_id'],
                        "status" => $trade['status'],
                    ]
                );
            }
            // Log::info('Master Worker: New Trades Updated...');

        }
    }



    public static function detectAndRestartStaledWorkers($account)
    {

        $url = "https://" . $account->domain_name . "/master-process/handle/" . config('binance.process_manager_client_key');

        $tradeAccount = $account->account_id;
        $staleTrades = DB::table('live_trades_master')->where('trade_acc', $tradeAccount)->where('domain_name', $account->domain_name)->where('last_worker_update_seconds', '>', 10)->get();


        // Send Restart Command to all workers that are stopped
        foreach ($staleTrades as $trade) {

            Log::info('Sending Restart Command...');

            $data = [
                'action' => 'FETCH_LIVE_TRADES_FUTURE',
                'account' => $account->account_id
            ];

            $response = Http::post($url, $data);

            if ($response->successful()) {
                $response = $response->json();
            }
        }
    }



    public static function handleStaleTrades($account)
    {

        $url = "https://" . $account->domain_name . "/master-process/handle/" . config('binance.process_manager_client_key');

        $staleTrades = DB::table('live_trades_master')->where('user_email', $account->email)->where('domain_name', $account->domain_name)->where('last_worker_update_seconds', '>', 10)->get();



        // Send Restart Command to all workers that are stopped
        foreach ($staleTrades as $trade) {

            $trader = self::getTraderIdFromDomainName($trade->domain_name, 'trader');

            $positionDetails = $trade->exchange === 'binance' ?
                BinanceApiService::getPositionDetails($trade->symbol, $trader)
                : HyperLiquidApiService::getPositionDetails($trade->symbol, $trader);

            if (!$positionDetails || $positionDetails['positionAmt'] == 0) {


                $trade->exchange === 'binance' ?
                    BinanceApiService::cancelOrder($trade->symbol, $trader, $trade->tp_order_id)
                    : HyperLiquidApiService::cancelOrder($trade->symbol, $trader, $trade->tp_order_id);

                $trade->exchange === 'binance' ?
                    BinanceApiService::cancelOrder($trade->symbol, $trader, $trade->sl_order_id)
                    : HyperLiquidApiService::cancelOrder($trade->symbol, $trader, $trade->sl_order_id);

                // Send a closing signal to accounts server
                $data = [
                    'action' => 'CLOSE_LIVE_TRADE',
                    'email' => $account->email,
                    'openOrderId' => $trade->orderId,
                ];

                $response = Http::post($url, $data);

                if ($response->successful()) {
                    return true;
                }
            }


            // Attempt a restart action
            $data = [
                'action' => 'RESTART_WORKER',
                'email' => $account->email,
                'openOrderId' => $trade->orderId,
                'workerId' => $trade->worker_id,
            ];

            $response = Http::post($url, $data);
        }
    }

    public static function getTraderIdFromDomainName($domainName, $role)
    {
        $user = User::where('domain_name', $domainName)->where('role', $role)->first();

        return $user ? $user->id : null;
    }


    public static function syncExternalUsers($domainName)
    {

        $url = "https://" . $domainName . "/master-process/handle/" . config('binance.process_manager_client_key');

        $data = [
            'action' => 'SYNC_USERS',
        ];
        $response = Http::post($url, $data);

        if ($response->successful()) {
            $response = $response->json();
            foreach ($response['data'] as $user) {


                DB::table('users')->updateOrInsert(
                    [
                        'email' => $user['email'],
                        'domain_name' => $response['domain'],
                    ],
                    [
                        'name' => $user['name'],
                        'password' => bcrypt('master@1234$'),
                        'role' => $user['role'],
                        'email_verified_at' => Carbon::parse($user['email_verified_at'])->toDateTimeString(),
                        'api_key' => $user['api_key'],
                        'api_secret' => $user['api_secret'],
                        'is_active' => $user['is_active'],
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString(),

                    ]

                );
            }
            return count($response['data']);
        }
        return false;
    }



    // Safe mode helper functions
    public static function enableSafeModeLive(string $symbol, string $position, $currentTimestamp, $trendType = null)
    {
        DB::table('safe_mode_worker_live')->updateOrInsert(
            ['symbol' => $symbol, 'position' => $position],
            [
                'safe_mode' => 1,
                'trend_type' => $trendType,
                'last_enabled_timestamp' => $currentTimestamp,
                'updated_at' => now()
            ]
        );
    }

    public static function getSafeModeStatus(string $symbol, string $position)
    {
        $entry = DB::table('safe_mode_worker_live')->where('symbol', $symbol)->where('position', $position)->first();

        return $entry ? $entry->safe_mode : null;
    }

    public static function getSafeModeEnableTime(string $symbol, string $position)
    {
        $entry = DB::table('safe_mode_worker_live')->where('symbol', $symbol)->where('position', $position)->first();

        return $entry ? $entry->last_enabled_timestamp : null;
    }


    public static function disableSafeModeLive(string $symbol, string $position)
    {
        DB::table('safe_mode_worker_live')->updateOrInsert(
            ['symbol' => $symbol, 'position' => $position],
            [
                'safe_mode' => 0,
                'updated_at' => now()
            ]
        );
    }



    // Accuracy Calculation Live

    public static function getAccuracy($position, $formula = 'Base Report', $tagName = null)
    {
        // Generate a unique cache key
        $cacheKey = "accuracy_{$position}_" . md5($formula . '_' . ($tagName ?? ''));

        // Attempt to get from cache first
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($position, $formula, $tagName) {
            try {
                // Build URL
                $url = "https://reachoutfans.com/csrf-free/safe-mode-accuracy/{$position}/{$formula}";

                if ($tagName) {
                    $url .= '/' . $tagName;
                }

                // Make HTTP GET request with timeout
                $response = Http::timeout(10)->get($url);

                if ($response->successful() && isset($response->json()['data'])) {
                    return $response->json()['data'];
                }

                // Log unexpected response
                Log::warning("getAccuracy: Unexpected response format", [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (RequestException $e) {
                Log::error("getAccuracy: HTTP request failed", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                Log::error("getAccuracy: Unexpected error", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback if request fails or returns no valid data
            return ['accuracy' => 0];
        });
    }

    public static function checkCandleClosing($data, $allowedTimeSec)
    {
        $timePastCurrentCandle = (now()->timestamp - ($data[count($data) - 1]['binance_timestamp'] / 1000));
        $isCandleClosing =  $timePastCurrentCandle <= $allowedTimeSec;
        return $isCandleClosing;
    }
    public static function checkCandleClosingAbsolute($interval, $allowedTimeSec)
    {
        $now = now();
        $minute = $now->minute;
        $second = $now->second;

        // Find how many minutes past the last 15-minute mark
        $minutesPast = $minute % CommonHelpers::$binanceIntervals[$interval];

        // Total seconds past last 15-minute mark
        $secondsPast = ($minutesPast * 60) + $second;
        // Check candle closing
        if ($secondsPast < $allowedTimeSec) {
            return true;
        }

        return false;
    }



    // Calculation functions

    public static function checkPivot($data, $index, $n = 2, $maxIndex = null)
    {
        $total = count($data);

        // Set maxIndex to array length if not provided
        if ($maxIndex === null) {
            $maxIndex = $total - 1;
        }

        // Ensure maxIndex doesn't exceed actual data bounds
        $maxIndex = min($maxIndex, $total - 1);

        // Make sure we have enough candles on the left side
        if ($index < $n) {
            return 'not_enough_data';
        }

        // Calculate available indexes on the right side
        $availableRight = $maxIndex - $index;
        if ($availableRight < 0) {
            dd("Test");
        }
        if ($availableRight < 1) {
            return 'not_enough_data';
        }

        // Use minimum of requested $n and available right indexes
        $rightIndexes = min($n, $availableRight);

        // Ensure current candle exists
        if (!isset($data[$index]['high'], $data[$index]['low'])) {
            return null;
        }

        $isHighPivot = true;
        $isLowPivot = true;

        $currentHigh = $data[$index]['high'];
        $currentLow = $data[$index]['low'];

        // Check left side
        for ($i = 1; $i <= $n; $i++) {
            if (!isset($data[$index - $i]['high'], $data[$index - $i]['low'])) {
                return null; // missing candle, bail
            }

            if ($currentHigh < $data[$index - $i]['high']) {
                $isHighPivot = false;
            }
            if ($currentLow > $data[$index - $i]['low']) {
                $isLowPivot = false;
            }

            if (!$isHighPivot && !$isLowPivot) {
                break; // no need to continue
            }
        }

        // Check right side
        for ($i = 1; $i <= $rightIndexes; $i++) {
            if (!isset($data[$index + $i]['high'], $data[$index + $i]['low'])) {
                return null; // missing candle, bail
            }

            if ($currentHigh < $data[$index + $i]['high']) {
                $isHighPivot = false;
            }
            if ($currentLow > $data[$index + $i]['low']) {
                $isLowPivot = false;
            }

            if (!$isHighPivot && !$isLowPivot) {
                break;
            }
        }

        if ($isHighPivot) {
            return 'high_pivot';
        } elseif ($isLowPivot) {
            return 'low_pivot';
        } else {
            return null;
        }
    }


    public static function checkPivotIndicator($data, $index, $n = 2, $maxIndex = null, $key = 'ma7')
    {
        $total = count($data);

        // Set maxIndex to array length if not provided
        if ($maxIndex === null) {
            $maxIndex = $total - 1;
        }

        // Ensure maxIndex doesn't exceed actual data bounds
        $maxIndex = min($maxIndex, $total - 1);

        // Make sure we have enough candles on the left side
        if ($index < $n) {
            return 'not_enough_data';
        }

        // Calculate available indexes on the right side
        $availableRight = $maxIndex - $index;

        // If no indexes available on right, return not_enough_data
        if ($availableRight < 1) {
            return 'not_enough_data';
        }

        // Use minimum of requested $n and available right indexes
        $rightIndexes = min($n, $availableRight);

        $isHighPivot = true;
        $isLowPivot = true;

        $currentHigh = $data[$index][$key];
        $currentLow = $data[$index][$key];

        // Check left side (full $n indexes)
        for ($i = 1; $i <= $n; $i++) {
            if ($currentHigh < $data[$index - $i][$key]) {
                $isHighPivot = false;
            }
            if ($currentLow > $data[$index - $i][$key]) {
                $isLowPivot = false;
            }
        }

        // Check right side (dynamic number of indexes - could be 1, 2, 3, 4, or more)
        for ($i = 1; $i <= $rightIndexes; $i++) {
            if ($currentHigh < $data[$index + $i][$key]) {
                $isHighPivot = false;
            }
            if ($currentLow > $data[$index + $i][$key]) {
                $isLowPivot = false;
            }
        }

        if ($isHighPivot) {
            return 'high_pivot';
        } elseif ($isLowPivot) {
            return 'low_pivot';
        } else {
            return null;
        }
    }
    // public static function checkDoubleTop($recentHigh, $secondRecentHigh, $tolerance)
    // {
    //     $priceDiff = abs($recentHigh['high'] - $secondRecentHigh['high']) / $secondRecentHigh['high'];
    //     return $priceDiff <= $tolerance && $recentHigh['high'] > $secondRecentHigh['high'] * 0.995;
    // }

    // public static function checkDoubleBottom($recentLow, $secondRecentLow, $tolerance)
    // {
    //     $priceDiff = abs($recentLow['low'] - $secondRecentLow['low']) / $secondRecentLow['low'];
    //     return $priceDiff <= $tolerance && $recentLow['low'] < $secondRecentLow['low'] * 1.005;
    // }

    public static function checkRsiDivergence($recentHigh, $secondRecentHigh, $threshold)
    {
        // Bearish divergence: Higher highs in price, lower highs in RSI
        return ($recentHigh['high'] > $secondRecentHigh['high']) &&
            ($recentHigh['rsi6'] < $secondRecentHigh['rsi6']) &&
            (($secondRecentHigh['rsi6'] - $recentHigh['rsi6']) >= $threshold);
    }

    public static function checkBullishRsiDivergence($recentLow, $secondRecentLow, $threshold)
    {
        // Bullish divergence: Lower lows in price, higher lows in RSI
        return ($recentLow['low'] < $secondRecentLow['low']) &&
            ($recentLow['rsi6'] > $secondRecentLow['rsi6']) &&
            (($recentLow['rsi6'] - $secondRecentLow['rsi6']) >= $threshold);
    }

    public static function checkValleyBreakout($data, $firstHighIndex, $secondHighIndex, $currentIndex)
    {
        // Find the lowest point between the two highs
        $valleyLow = PHP_FLOAT_MAX;
        for ($i = $firstHighIndex + 1; $i < $secondHighIndex; $i++) {
            if ($data[$i]['low'] < $valleyLow) {
                $valleyLow = $data[$i]['low'];
            }
        }

        // Check if current price has broken below the valley
        return $data[$currentIndex]['close'] < $valleyLow * 0.999; // 0.1% below valley
    }

    // Additional Risk Management
    public static function calculatePositionSize($accountBalance, $riskPercent, $entryPrice, $stopLossPrice)
    {
        $riskAmount = $accountBalance * ($riskPercent / 100);
        $priceRisk = abs($entryPrice - $stopLossPrice);
        return $riskAmount / $priceRisk;
    }

    // Market Structure Analysis
    public static function isInDowntrend($data, $currentIndex, $lookback = 50)
    {
        if ($currentIndex < $lookback) return false;

        $recentHighs = [];
        for ($i = $currentIndex - $lookback; $i <= $currentIndex; $i++) {
            $pivot = CommonHelpers::checkPivot($data, $i, 5);
            if ($pivot === 'high_pivot') {
                $recentHighs[] = $data[$i]['high'];
            }
        }

        // Check if recent highs are generally decreasing
        if (count($recentHighs) >= 2) {
            return end($recentHighs) < $recentHighs[0];
        }

        return false;
    }





    // public static function detectMarketStructure($data, $index, $options = [])
    // {
    //     // Default parameters
    //     $lookback = $options['lookback'] ?? 50;
    //     $pivot_window = $options['pivot_window'] ?? 5;
    //     $zone_percentage = $options['zone_percentage'] ?? 0.002; // 0.2%
    //     $min_touches = $options['min_touches'] ?? 3;
    //     $min_strength = $options['min_strength'] ?? 2;

    //     // Ensure we have enough data
    //     $start_index = max(0, $index - $lookback);
    //     $end_index = min(count($data) - 1, $index);

    //     if ($end_index - $start_index < $pivot_window * 2) {
    //         return ['support_levels' => [], 'resistance_levels' => []];
    //     }

    //     // Find pivot points (support and resistance)
    //     $resistance_pivots = self::findPivotHighs($data, $start_index, $end_index, $pivot_window);
    //     $support_pivots = self::findPivotLows($data, $start_index, $end_index, $pivot_window);

    //     // Group similar levels and calculate strength
    //     $resistance_levels = self::groupAndAnalyzeLevels($data, $resistance_pivots, $start_index, $end_index, $zone_percentage, $min_touches, 'resistance');
    //     $support_levels = self::groupAndAnalyzeLevels($data, $support_pivots, $start_index, $end_index, $zone_percentage, $min_touches, 'support');

    //     // Filter by minimum strength
    //     $resistance_levels = array_filter($resistance_levels, function ($level) use ($min_strength) {
    //         return $level['strength'] >= $min_strength;
    //     });

    //     $support_levels = array_filter($support_levels, function ($level) use ($min_strength) {
    //         return $level['strength'] >= $min_strength;
    //     });

    //     // Sort by strength (strongest first)
    //     usort($resistance_levels, function ($a, $b) {
    //         return $b['strength'] <=> $a['strength'];
    //     });
    //     usort($support_levels, function ($a, $b) {
    //         return $b['strength'] <=> $a['strength'];
    //     });

    //     return [
    //         'support_levels' => array_values($support_levels),
    //         'resistance_levels' => array_values($resistance_levels)
    //     ];
    // }

    public static function findPivotHighs($data, $start, $end, $window)
    {
        $pivots = [];

        for ($i = $start + $window; $i <= $end - $window; $i++) {
            $current_high = $data[$i]['high'];
            $is_pivot = true;

            // Check if current high is higher than surrounding highs
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j != $i && $data[$j]['high'] >= $current_high) {
                    $is_pivot = false;
                    break;
                }
            }

            if ($is_pivot) {
                $pivots[] = [
                    'index' => $i,
                    'price' => $current_high,
                    'type' => 'resistance'
                ];
            }
        }

        return $pivots;
    }

    public static function findPivotLows($data, $start, $end, $window)
    {
        $pivots = [];

        for ($i = $start + $window; $i <= $end - $window; $i++) {
            $current_low = $data[$i]['low'];
            $is_pivot = true;

            // Check if current low is lower than surrounding lows
            for ($j = $i - $window; $j <= $i + $window; $j++) {
                if ($j != $i && $data[$j]['low'] <= $current_low) {
                    $is_pivot = false;
                    break;
                }
            }

            if ($is_pivot) {
                $pivots[] = [
                    'index' => $i,
                    'price' => $current_low,
                    'type' => 'support'
                ];
            }
        }

        return $pivots;
    }

    public static function groupAndAnalyzeLevels($data, $pivots, $start, $end, $zone_percentage, $min_touches, $type)
    {
        if (empty($pivots)) return [];

        $levels = [];
        $used_pivots = [];

        foreach ($pivots as $i => $pivot) {
            if (in_array($i, $used_pivots)) continue;

            $base_price = $pivot['price'];
            $zone_size = $base_price * $zone_percentage;
            $zone_upper = $base_price + $zone_size;
            $zone_lower = $base_price - $zone_size;

            $grouped_pivots = [$pivot];
            $used_pivots[] = $i;

            // Find other pivots within the same zone
            foreach ($pivots as $j => $other_pivot) {
                if ($i != $j && !in_array($j, $used_pivots)) {
                    if ($other_pivot['price'] >= $zone_lower && $other_pivot['price'] <= $zone_upper) {
                        $grouped_pivots[] = $other_pivot;
                        $used_pivots[] = $j;
                    }
                }
            }

            // Calculate average price for the level
            $avg_price = array_sum(array_column($grouped_pivots, 'price')) / count($grouped_pivots);

            // Recalculate zone around average price
            $final_zone_size = $avg_price * $zone_percentage;
            $final_zone_upper = $avg_price + $final_zone_size;
            $final_zone_lower = $avg_price - $final_zone_size;

            // Count total touches (including wicks touching the zone)
            $touches = self::countTouches($data, $start, $end, $final_zone_upper, $final_zone_lower, $type);

            if (count($touches) >= $min_touches) {
                $levels[] = [
                    'price' => round($avg_price, 5),
                    'zone_upper' => round($final_zone_upper, 5),
                    'zone_lower' => round($final_zone_lower, 5),
                    'strength' => count($touches),
                    'type' => $type,
                    'pivot_count' => count($grouped_pivots),
                    'touches' => $touches,
                    'first_touch' => min($touches),
                    'last_touch' => max($touches)
                ];
            }
        }

        return $levels;
    }

    public static function countTouches($data, $start, $end, $zone_upper, $zone_lower, $type)
    {
        $touches = [];

        for ($i = $start; $i <= $end; $i++) {
            $candle = $data[$i];
            $touched = false;

            if ($type === 'resistance') {
                // Check if high touched resistance zone
                if ($candle['high'] >= $zone_lower && $candle['high'] <= $zone_upper) {
                    $touched = true;
                }
            } else { // support
                // Check if low touched support zone
                if ($candle['low'] >= $zone_lower && $candle['low'] <= $zone_upper) {
                    $touched = true;
                }
            }

            if ($touched) {
                $touches[] = $i;
            }
        }

        // Remove consecutive touches (only count significant bounces)
        return self::filterConsecutiveTouches($touches);
    }

    public static function filterConsecutiveTouches($touches, $min_gap = 3)
    {
        if (empty($touches)) return [];

        $filtered = [$touches[0]];

        for ($i = 1; $i < count($touches); $i++) {
            if ($touches[$i] - end($filtered) >= $min_gap) {
                $filtered[] = $touches[$i];
            }
        }

        return $filtered;
    }


    public static function getPivotsRange($data, $percentR = 1)
    {
        $prices = array_column($data, 'price');
        sort($prices);

        // Step 2: Sliding window to find max count within 1% width
        $maxCount = 0;
        $bestRange = [0, 0];

        for ($i = 0; $i < count($prices); $i++) {
            $start = $prices[$i];
            $end = $start * (1 + $percentR / 100); // 1% upper limit
            $count = 0;

            // Count how many fall within [start, end]
            for ($j = $i; $j < count($prices); $j++) {
                if ($prices[$j] <= $end) {
                    $count++;
                } else {
                    break;
                }
            }

            if ($count > $maxCount) {
                $maxCount = $count;
                $bestRange = [$start, $end];
            }
        }
        return $bestRange;
    }





    public static function getGoldCandlestickDataTwelve($interval = '1day', $apiKey = 'demo', $outputsize = 30)
    {
        $symbol = 'XAU/USD';

        $url = "https://api.twelvedata.com/time_series?symbol={$symbol}&interval={$interval}&outputsize={$outputsize}&apikey={$apiKey}";

        $response = file_get_contents($url);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);

        if (!$data || isset($data['status']) && $data['status'] === 'error') {
            return false;
        }

        if (!isset($data['values'])) {
            return false;
        }

        $candlesticks = [];

        foreach ($data['values'] as $item) {
            $candlesticks[] = [
                'timestamp' => $item['datetime'],
                'datetime' => $item['datetime'],
                'open' => floatval($item['open']),
                'high' => floatval($item['high']),
                'low' => floatval($item['low']),
                'close' => floatval($item['close']),
                'volume' => isset($item['volume']) ? intval($item['volume']) : 0
            ];
        }

        // Sort by timestamp (oldest first)
        usort($candlesticks, function ($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        return $candlesticks;
    }



    public static function checkCandleOverlap($data, $index, $thresholdPrice, $type = 'support', $binSize = 0.05)
    {
        // Create the zone boundaries
        $zoneMax = $thresholdPrice * (1 + $binSize / 100);
        $zoneMin = $thresholdPrice * (1 - $binSize / 100);

        // Get candle data
        $candle = $data[$index];
        $open = $candle['open'];
        $close = $candle['close'];
        $high = $candle['high'];
        $low = $candle['low'];

        // Calculate candle body range
        $bodyMax = max($open, $close);
        $bodyMin = min($open, $close);

        // Determine candle direction
        $isBullish = $close > $open;
        $isBearish = $close < $open;
        $isDoji = $close == $open;

        // Calculate overlap percentages for more precise analysis
        $bodyInZone = self::calculateOverlapPercentage($bodyMin, $bodyMax, $zoneMin, $zoneMax);
        $wickInZone = self::calculateOverlapPercentage($low, $high, $zoneMin, $zoneMax);

        // Analyze body position relative to zone
        $bodyPosition = self::getBodyPosition($bodyMin, $bodyMax, $zoneMin, $zoneMax);

        // Analyze wick interactions
        $wickAnalysis = self::analyzeWickInteraction($low, $high, $bodyMin, $bodyMax, $zoneMin, $zoneMax);

        // Build comprehensive response
        $response = [
            'zone_type' => $type,
            'zone_boundaries' => [
                'min' => $zoneMin,
                'max' => $zoneMax,
                'threshold' => $thresholdPrice
            ],
            'candle_data' => [
                'open' => $open,
                'close' => $close,
                'high' => $high,
                'low' => $low,
                'body_max' => $bodyMax,
                'body_min' => $bodyMin,
                'direction' => $isBullish ? 'bullish' : ($isBearish ? 'bearish' : 'doji')
            ],
            'overlap_analysis' => [
                'body_in_zone_percent' => $bodyInZone,
                'total_wick_in_zone_percent' => $wickInZone,
                'body_position' => $bodyPosition,
                'wick_analysis' => $wickAnalysis
            ],
            'interaction_type' => self::determineInteractionType($bodyPosition, $wickAnalysis, $type, $isBullish, $isBearish),
            'strength' => self::calculateInteractionStrength($bodyInZone, $wickInZone, $bodyPosition, $wickAnalysis),

        ];

        return $response;
    }



    public static function minutesUntilNextHour(?array $candle = null): int
    {
        if ($candle === null) {
            // Use system time
            $time = Carbon::now();
        } else {
            // Candle end time is already in milliseconds → convert properly
            $candleEndTimestamp = $candle['binance_timestamp']
                + (self::$binanceIntervals[$candle['interval']] * 60 * 1000);

            $time = Carbon::createFromTimestampMs($candleEndTimestamp);
        }

        $nextHour = $time->copy()->addHour()->startOfHour();

        return $time->diffInMinutes($nextHour);
    }
    public static function calculateOverlapPercentage($rangeMin, $rangeMax, $zoneMin, $zoneMax)
    {
        // Calculate what percentage of the range overlaps with the zone
        $overlapMin = max($rangeMin, $zoneMin);
        $overlapMax = min($rangeMax, $zoneMax);

        if ($overlapMin >= $overlapMax) {
            return 0; // No overlap
        }

        $overlapSize = $overlapMax - $overlapMin;
        $rangeSize = $rangeMax - $rangeMin;

        return $rangeSize > 0 ? ($overlapSize / $rangeSize) * 100 : 0;
    }

    public static function getBodyPosition($bodyMin, $bodyMax, $zoneMin, $zoneMax)
    {
        // Determine body position relative to zone
        if ($bodyMax < $zoneMin) {
            return 'completely_below';
        } elseif ($bodyMin > $zoneMax) {
            return 'completely_above';
        } elseif ($bodyMin >= $zoneMin && $bodyMax <= $zoneMax) {
            return 'completely_within';
        } elseif ($bodyMin < $zoneMin && $bodyMax > $zoneMax) {
            return 'engulfing_zone';
        } elseif ($bodyMin < $zoneMin && $bodyMax >= $zoneMin && $bodyMax <= $zoneMax) {
            return 'partial_from_below';
        } elseif ($bodyMin >= $zoneMin && $bodyMin <= $zoneMax && $bodyMax > $zoneMax) {
            return 'partial_from_above';
        } else {
            return 'undefined';
        }
    }

    public static function analyzeWickInteraction($low, $high, $bodyMin, $bodyMax, $zoneMin, $zoneMax)
    {
        $upperWick = $high - $bodyMax;
        $lowerWick = $bodyMin - $low;

        $analysis = [
            'upper_wick_size' => $upperWick,
            'lower_wick_size' => $lowerWick,
            'upper_wick_touches_zone' => false,
            'lower_wick_touches_zone' => false,
            'upper_wick_penetrates_zone' => false,
            'lower_wick_penetrates_zone' => false,
            'rejection_pattern' => 'none'
        ];

        // Check upper wick interaction
        if ($upperWick > 0) {
            if ($high >= $zoneMin && $high <= $zoneMax) {
                $analysis['upper_wick_touches_zone'] = true;
            }
            if ($bodyMax < $zoneMin && $high >= $zoneMin) {
                $analysis['upper_wick_penetrates_zone'] = true;
            }
        }

        // Check lower wick interaction
        if ($lowerWick > 0) {
            if ($low >= $zoneMin && $low <= $zoneMax) {
                $analysis['lower_wick_touches_zone'] = true;
            }
            if ($bodyMin > $zoneMax && $low <= $zoneMax) {
                $analysis['lower_wick_penetrates_zone'] = true;
            }
        }

        // Identify rejection patterns
        if ($analysis['upper_wick_penetrates_zone'] && $upperWick > ($bodyMax - $bodyMin)) {
            $analysis['rejection_pattern'] = 'upper_rejection';
        } elseif ($analysis['lower_wick_penetrates_zone'] && $lowerWick > ($bodyMax - $bodyMin)) {
            $analysis['rejection_pattern'] = 'lower_rejection';
        }

        return $analysis;
    }

    public static function determineInteractionType($bodyPosition, $wickAnalysis, $type, $isBullish, $isBearish)
    {
        // Determine the primary interaction type based on body position and wick analysis
        switch ($bodyPosition) {
            case 'completely_below':
                if ($wickAnalysis['upper_wick_penetrates_zone']) {
                    return $type === 'support' ? 'wick_test_from_below' : 'wick_test_resistance';
                }
                return $type === 'support' ? 'below_support' : 'below_resistance';

            case 'completely_above':
                if ($wickAnalysis['lower_wick_penetrates_zone']) {
                    return $type === 'support' ? 'wick_test_from_above' : 'wick_retest_from_above';
                }
                return $type === 'support' ? 'above_support' : 'above_resistance';

            case 'completely_within':
                return $type === 'support' ? 'trading_within_support' : 'trading_within_resistance';

            case 'engulfing_zone':
                if ($isBullish) {
                    return $type === 'support' ? 'bullish_engulfing_support' : 'bullish_breakthrough_resistance';
                } elseif ($isBearish) {
                    return $type === 'support' ? 'bearish_breakdown_support' : 'bearish_rejection_resistance';
                }
                return $type === 'support' ? 'engulfing_support' : 'engulfing_resistance';

            case 'partial_from_below':
                if ($isBullish) {
                    return $type === 'support' ? 'bullish_bounce_support' : 'bullish_break_resistance';
                } elseif ($isBearish) {
                    return $type === 'support' ? 'bearish_retest_support' : 'bearish_rejection_resistance';
                }
                return $type === 'support' ? 'partial_break_above_support' : 'partial_break_resistance';

            case 'partial_from_above':
                if ($isBullish) {
                    return $type === 'support' ? 'bullish_retest_support' : 'bullish_recovery_resistance';
                } elseif ($isBearish) {
                    return $type === 'support' ? 'bearish_break_support' : 'bearish_pullback_resistance';
                }
                return $type === 'support' ? 'partial_break_below_support' : 'partial_pullback_resistance';

            default:
                return 'undefined_interaction';
        }
    }

    public static function calculateInteractionStrength($bodyInZone, $wickInZone, $bodyPosition, $wickAnalysis)
    {
        $strength = 0;

        // Base strength on body interaction
        switch ($bodyPosition) {
            case 'completely_within':
                $strength += 80;
                break;
            case 'engulfing_zone':
                $strength += 90;
                break;
            case 'partial_from_below':
            case 'partial_from_above':
                $strength += 60;
                break;
            case 'completely_below':
            case 'completely_above':
                $strength += 20;
                break;
        }

        // Add strength based on wick interaction
        if ($wickAnalysis['upper_wick_penetrates_zone'] || $wickAnalysis['lower_wick_penetrates_zone']) {
            $strength += 30;
        }

        // Add strength based on rejection patterns
        if ($wickAnalysis['rejection_pattern'] !== 'none') {
            $strength += 40;
        }

        // Normalize to 0-100 scale
        $strength = min(100, max(0, $strength));

        // Return descriptive strength
        if ($strength >= 80) return 'very_strong';
        if ($strength >= 60) return 'strong';
        if ($strength >= 40) return 'moderate';
        if ($strength >= 20) return 'weak';
        return 'very_weak';
    }

    public static function generateTradingSignal($bodyPosition, $wickAnalysis, $type, $isBullish, $isBearish)
    {
        $signal = [
            'direction' => 'neutral',
            'confidence' => 'low',
            'action' => 'wait',
            'description' => ''
        ];

        if ($type === 'support') {
            // Support level analysis
            if ($bodyPosition === 'partial_from_below' && $isBullish) {
                $signal = [
                    'direction' => 'bullish',
                    'confidence' => 'high',
                    'action' => 'buy',
                    'description' => 'Bullish bounce from support level'
                ];
            } elseif ($wickAnalysis['rejection_pattern'] === 'lower_rejection') {
                $signal = [
                    'direction' => 'bullish',
                    'confidence' => 'medium',
                    'action' => 'buy',
                    'description' => 'Rejection at support with long lower wick'
                ];
            } elseif ($bodyPosition === 'partial_from_above' && $isBearish) {
                $signal = [
                    'direction' => 'bearish',
                    'confidence' => 'high',
                    'action' => 'sell',
                    'description' => 'Support level breakdown'
                ];
            }
        } else {
            // Resistance level analysis
            if ($bodyPosition === 'partial_from_below' && $isBullish) {
                $signal = [
                    'direction' => 'bullish',
                    'confidence' => 'high',
                    'action' => 'buy',
                    'description' => 'Bullish breakout through resistance'
                ];
            } elseif ($wickAnalysis['rejection_pattern'] === 'upper_rejection') {
                $signal = [
                    'direction' => 'bearish',
                    'confidence' => 'medium',
                    'action' => 'sell',
                    'description' => 'Rejection at resistance with long upper wick'
                ];
            } elseif ($bodyPosition === 'partial_from_above' && $isBearish) {
                $signal = [
                    'direction' => 'bearish',
                    'confidence' => 'medium',
                    'action' => 'sell',
                    'description' => 'Failed breakout, pulling back from resistance'
                ];
            }
        }

        return $signal;
    }
    public static function calculateMA($data, $index, $length, $priceKey = 'close')
    {
        if ($index + 1 < $length) {
            return null; // Not enough data
        }

        $sum = 0;
        for ($i = $index - $length + 1; $i <= $index; $i++) {
            $sum += $data[$i][$priceKey];
        }

        return $sum / $length;
    }

    public static function isWilliamsFractal($data, $index)
    {
        if ($index < 4) {
            return null; // Not enough candles to form a fractal
        }

        // Extract highs and lows for last 5 candles (including current)
        $highs = array_column(array_slice($data, $index - 4, 5), 'high');
        $lows = array_column(array_slice($data, $index - 4, 5), 'low');

        // Fractal is always at 3rd candle in the 5-candle window
        $mid = 2;

        // Bearish fractal (local top)
        if (
            $highs[$mid] > $highs[0] && $highs[$mid] > $highs[1] &&
            $highs[$mid] > $highs[3] && $highs[$mid] > $highs[4]
        ) {
            return 'bearish'; // Sell signal
        }

        // Bullish fractal (local bottom)
        if (
            $lows[$mid] < $lows[0] && $lows[$mid] < $lows[1] &&
            $lows[$mid] < $lows[3] && $lows[$mid] < $lows[4]
        ) {
            return 'bullish'; // Buy signal
        }

        return null;
    }

    public static function getLineEquation($x1, $y1, $x2, $y2)
    {
        // Handle vertical line case
        if ($x1 == $x2) {
            return [
                'm' => INF,
                'c' => null,
                'angle_deg' => 90,
                'equation' => "x = {$x1}"
            ];
        }

        // Calculate slope (m)
        $m = ($y2 - $y1) / ($x2 - $x1);

        // Calculate intercept (c)
        $c = $y1 - ($m * $x1);

        // Normalize angle between 0°–90°
        $angle = abs(rad2deg(atan($m)));

        return [
            'm' => $m,
            'c' => $c,
            'angle_deg' => $angle,
            'equation' => "y = {$m}x + {$c}"
        ];
    }

    public static function mapValueToRange($value, $bottom, $top)
    {
        // Null protection
        if ($value === null || $bottom === null || $top === null) {
            return null;
        }

        // If top == bottom, the range size is 0. Handle this case (e.g., price is at 0% or 100%).
        if ($top == $bottom) {
            return ($value == $top) ? 100 : 0;
        }

        // Calculate the percentage position of $value within the range ($bottom to $top)
        $range = $top - $bottom;
        $position = $value - $bottom;

        $percentage = ($position / $range) * 100;

        return $percentage;
    }

    public static function getRecentPivot($data, $index, $pivotType = 'high', $pivotWidth = 3, $pivotMode = 'wick', $thresholdValue = null)
    {
        if ($pivotType === 'high') {

            $loopIndex = $index - $pivotWidth;

            while ($loopIndex > $pivotWidth) {
                $pivotModeName = $pivotMode === 'wick' ? 'high' : $pivotMode;

                $pivot = CommonHelpers::checkPivotIndicator($data, $loopIndex, $pivotWidth, null, $pivotModeName);



                if ($pivot === 'high_pivot') {

                    if ($thresholdValue && $data[$loopIndex][$pivotModeName] <= $thresholdValue) {
                        $loopIndex--;
                        continue;
                    }

                    return [
                        'index' => $loopIndex,
                        'mode' => $pivotMode,
                        'width' => $pivotWidth,
                        'type' => 'high',
                        'value' => $data[$loopIndex][$pivotModeName],

                    ];
                }

                $loopIndex--;
            }
        } else if ($pivotType === 'low') {
            $loopIndex = $index - $pivotWidth;

            while ($loopIndex > $pivotWidth) {
                $pivotModeName = $pivotMode === 'wick' ? 'low' : $pivotMode;

                $pivot = CommonHelpers::checkPivotIndicator($data, $loopIndex, $pivotWidth, null, $pivotModeName);



                if ($pivot === 'low_pivot') {

                    if ($thresholdValue && $data[$loopIndex][$pivotModeName] >= $thresholdValue) {
                        $loopIndex--;
                        continue;
                    }
                    return [
                        'index' => $loopIndex,
                        'mode' => $pivotMode,
                        'width' => $pivotWidth,
                        'type' => 'low',
                        'value' => $data[$loopIndex][$pivotModeName],
                    ];
                }

                $loopIndex--;
            }
        }

        return null;
    }
    public static function getBreakoutPriceFromTrendLine($data, $index, $recentTrendline)
    {
        if (
            !$recentTrendline
            ||
            !isset($recentTrendline['m'])
            ||
            !isset($recentTrendline['c'])
        ) {
            return null;
        }

        return ($recentTrendline['m'] * $data[$index]['binance_timestamp'] + $recentTrendline['c']);
    }
    /**
     * Perform linear regression on an array of (x, y) points.
     *
     * @param array $points Array of ['x' => value, 'y' => value]
     * @return array|null ['m' => slope, 'c' => intercept, 'r2' => accuracy, 'angle_deg' => angle in degrees, 'equation' => string]
     */
    public static function linearRegression(array $points)
    {
        $n = count($points);
        if ($n < 2) {
            return null; // Need at least 2 points
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;
        foreach ($points as $p) {
            $x = $p['x'];
            $y = $p['y'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $den = ($n * $sumXX - $sumX * $sumX);
        if ($den == 0) {
            return null; // vertical line case
        }

        // Regression coefficients
        $m = ($n * $sumXY - $sumX * $sumY) / $den;
        $c = ($sumY - $m * $sumX) / $n;

        // Compute R² (goodness of fit)
        $meanY = $sumY / $n;
        $ssTot = 0;
        $ssRes = 0;
        foreach ($points as $p) {
            $yPred = $m * $p['x'] + $c;
            $ssTot += pow($p['y'] - $meanY, 2);
            $ssRes += pow($p['y'] - $yPred, 2);
        }
        $r2 = ($ssTot == 0) ? 1 : 1 - ($ssRes / $ssTot);

        // Angle in degrees (normalized 0–90)
        $angle = abs(rad2deg(atan($m)));

        return [
            'm' => $m,
            'c' => $c,
            'r2' => $r2,
            'angle_deg' => $angle,
            'equation' => "y = {$m}x + {$c}"
        ];
    }


    public static function estimateLine($x, $x1, $y1, $x2, $y2)
    {
        if ($x2 - $x1 == 0) {
            return $y2;
        }

        // Slope (m) = (y2 - y1) / (x2 - x1)
        $m = ($y2 - $y1) / ($x2 - $x1);

        // y = m * (x - x1) + y1
        $y = $m * ($x - $x1) + $y1;

        return $y;
    }


















    // Trend Analysis
    public static function updateTrends($symbol, $data, $index, $lookBack = 300)
    {
        // Ensure we have enough data
        if ($index < 50 || count($data) < 50) {
            return [
                'trend' => 'insufficient_data',
                'confidence' => 0,
                'structure' => null,
                'details' => 'Not enough data for analysis'
            ];
        }

        // Define the range to analyze
        $startIndex = max(0, $index - $lookBack);
        $endIndex = min($index, count($data) - 1);

        // Find all pivots in the range
        $pivots = self::findPivotsInRange($data, $startIndex, $endIndex);

        // Detect market structure
        $structure = self::detectMarketStructure($data, $pivots, $index);

        // Determine trend based on structure and current position
        $trendAnalysis = self::analyzeTrend($data, $structure, $index);

        return $trendAnalysis;
    }

    public static function findPivotsInRange($data, $startIndex, $endIndex)
    {
        $pivots = [
            'highs' => [],
            'lows' => []
        ];

        // Find all pivot points in the range
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $pivot = self::checkPivot($data, $i, 5, $endIndex);

            if ($pivot === 'high_pivot') {
                $pivots['highs'][] = [
                    'index' => $i,
                    'price' => $data[$i]['high'],
                    'timestamp' => $data[$i]['timestamp'] ?? $i
                ];
            } elseif ($pivot === 'low_pivot') {
                $pivots['lows'][] = [
                    'index' => $i,
                    'price' => $data[$i]['low'],
                    'timestamp' => $data[$i]['timestamp'] ?? $i
                ];
            }
        }

        // Sort pivots by index (chronological order)
        usort($pivots['highs'], function ($a, $b) {
            return $a['index'] - $b['index'];
        });

        usort($pivots['lows'], function ($a, $b) {
            return $a['index'] - $b['index'];
        });

        return $pivots;
    }

    public static function detectMarketStructure($data, $pivots, $currentIndex)
    {
        $structure = [
            'type' => null,
            'confidence' => 0,
            'key_levels' => [],
            'breakout_direction' => null,
            'support_resistance' => []
        ];

        // Get recent highs and lows (last 4-6 of each)
        $recentHighs = array_slice($pivots['highs'], -6);
        $recentLows = array_slice($pivots['lows'], -6);

        // Check for double top
        $doubleTop = self::checkDoubleTop($recentHighs, $data[$currentIndex]['close']);
        if ($doubleTop['detected']) {
            $structure['type'] = 'double_top';
            $structure['confidence'] = $doubleTop['confidence'];
            $structure['key_levels'] = $doubleTop['levels'];
            $structure['breakout_direction'] = $doubleTop['breakout_direction'];
        }

        // Check for double bottom
        $doubleBottom = self::checkDoubleBottom($recentLows, $data[$currentIndex]['close']);
        if ($doubleBottom['detected']) {
            $structure['type'] = 'double_bottom';
            $structure['confidence'] = $doubleBottom['confidence'];
            $structure['key_levels'] = $doubleBottom['levels'];
            $structure['breakout_direction'] = $doubleBottom['breakout_direction'];
        }

        // Check for head and shoulders
        $headShoulders = self::checkHeadAndShoulders($recentHighs, $recentLows, $data[$currentIndex]['close']);
        if ($headShoulders['detected']) {
            $structure['type'] = 'head_and_shoulders';
            $structure['confidence'] = $headShoulders['confidence'];
            $structure['key_levels'] = $headShoulders['levels'];
            $structure['breakout_direction'] = $headShoulders['breakout_direction'];
        }

        // Check for ascending/descending triangles
        $triangle = self::checkTrianglePattern($recentHighs, $recentLows, $data[$currentIndex]['close']);
        if ($triangle['detected']) {
            $structure['type'] = $triangle['type'];
            $structure['confidence'] = $triangle['confidence'];
            $structure['key_levels'] = $triangle['levels'];
            $structure['breakout_direction'] = $triangle['breakout_direction'];
        }

        // Check for support/resistance breakouts
        $breakout = self::checkSupportResistanceBreakout($recentHighs, $recentLows, $data, $currentIndex);
        if ($breakout['detected']) {
            $structure['type'] = 'breakout';
            $structure['confidence'] = $breakout['confidence'];
            $structure['key_levels'] = $breakout['levels'];
            $structure['breakout_direction'] = $breakout['direction'];
        }

        // Identify current support and resistance levels
        $structure['support_resistance'] = self::identifySupportResistance($recentHighs, $recentLows);

        return $structure;
    }

    public static function checkDoubleTop($highs, $currentPrice)
    {
        if (count($highs) < 2) {
            return ['detected' => false, 'confidence' => 0];
        }

        // Get the two highest recent highs
        $sortedHighs = $highs;
        usort($sortedHighs, function ($a, $b) {
            return $b['price'] - $a['price'];
        });

        $firstHigh = $sortedHighs[0];
        $secondHigh = $sortedHighs[1];

        // Check if they are similar in price (within 2%)
        $priceDifference = abs($firstHigh['price'] - $secondHigh['price']) / $firstHigh['price'];

        if ($priceDifference <= 0.02) {
            // Find the low between the two highs
            $minIndex = min($firstHigh['index'], $secondHigh['index']);
            $maxIndex = max($firstHigh['index'], $secondHigh['index']);

            $necklinePrice = $firstHigh['price']; // Simplified neckline

            // Determine breakout direction
            $breakoutDirection = null;
            if ($currentPrice < $necklinePrice * 0.98) {
                $breakoutDirection = 'bearish';
            }

            return [
                'detected' => true,
                'confidence' => 85,
                'levels' => [$firstHigh['price'], $secondHigh['price']],
                'breakout_direction' => $breakoutDirection
            ];
        }

        return ['detected' => false, 'confidence' => 0];
    }

    public static function checkDoubleBottom($lows, $currentPrice)
    {
        if (count($lows) < 2) {
            return ['detected' => false, 'confidence' => 0];
        }

        // Get the two lowest recent lows
        $sortedLows = $lows;
        usort($sortedLows, function ($a, $b) {
            return $a['price'] - $b['price'];
        });

        $firstLow = $sortedLows[0];
        $secondLow = $sortedLows[1];

        // Check if they are similar in price (within 2%)
        $priceDifference = abs($firstLow['price'] - $secondLow['price']) / $firstLow['price'];

        if ($priceDifference <= 0.02) {
            $necklinePrice = $firstLow['price']; // Simplified neckline

            // Determine breakout direction
            $breakoutDirection = null;
            if ($currentPrice > $necklinePrice * 1.02) {
                $breakoutDirection = 'bullish';
            }

            return [
                'detected' => true,
                'confidence' => 85,
                'levels' => [$firstLow['price'], $secondLow['price']],
                'breakout_direction' => $breakoutDirection
            ];
        }

        return ['detected' => false, 'confidence' => 0];
    }

    public static function checkHeadAndShoulders($highs, $lows, $currentPrice)
    {
        if (count($highs) < 3) {
            return ['detected' => false, 'confidence' => 0];
        }

        // Get last 3 highs
        $lastThreeHighs = array_slice($highs, -3);

        // Check if middle high is significantly higher than the other two
        $leftShoulder = $lastThreeHighs[0];
        $head = $lastThreeHighs[1];
        $rightShoulder = $lastThreeHighs[2];

        if (
            $head['price'] > $leftShoulder['price'] * 1.02 &&
            $head['price'] > $rightShoulder['price'] * 1.02 &&
            abs($leftShoulder['price'] - $rightShoulder['price']) / $leftShoulder['price'] <= 0.03
        ) {

            // Calculate neckline (simplified)
            $necklinePrice = min($leftShoulder['price'], $rightShoulder['price']);

            $breakoutDirection = null;
            if ($currentPrice < $necklinePrice * 0.98) {
                $breakoutDirection = 'bearish';
            }

            return [
                'detected' => true,
                'confidence' => 90,
                'levels' => [$leftShoulder['price'], $head['price'], $rightShoulder['price']],
                'breakout_direction' => $breakoutDirection
            ];
        }

        return ['detected' => false, 'confidence' => 0];
    }

    public static function checkTrianglePattern($highs, $lows, $currentPrice)
    {
        if (count($highs) < 3 || count($lows) < 3) {
            return ['detected' => false, 'confidence' => 0];
        }

        // Check for ascending triangle (highs level, lows ascending)
        $recentHighs = array_slice($highs, -3);
        $recentLows = array_slice($lows, -3);

        // Check if highs are relatively flat
        $highPrices = array_column($recentHighs, 'price');
        $highsFlat = (max($highPrices) - min($highPrices)) / max($highPrices) <= 0.02;

        // Check if lows are ascending
        $lowPrices = array_column($recentLows, 'price');
        $lowsAscending = $lowPrices[2] > $lowPrices[1] && $lowPrices[1] > $lowPrices[0];

        if ($highsFlat && $lowsAscending) {
            $resistanceLevel = max($highPrices);
            $breakoutDirection = null;
            if ($currentPrice > $resistanceLevel * 1.01) {
                $breakoutDirection = 'bullish';
            }

            return [
                'detected' => true,
                'type' => 'ascending_triangle',
                'confidence' => 80,
                'levels' => [$resistanceLevel, $lowPrices[2]],
                'breakout_direction' => $breakoutDirection
            ];
        }

        // Check for descending triangle (lows level, highs descending)
        $lowsFlat = (max($lowPrices) - min($lowPrices)) / max($lowPrices) <= 0.02;
        $highsDescending = $highPrices[2] < $highPrices[1] && $highPrices[1] < $highPrices[0];

        if ($lowsFlat && $highsDescending) {
            $supportLevel = min($lowPrices);
            $breakoutDirection = null;
            if ($currentPrice < $supportLevel * 0.99) {
                $breakoutDirection = 'bearish';
            }

            return [
                'detected' => true,
                'type' => 'descending_triangle',
                'confidence' => 80,
                'levels' => [$supportLevel, $highPrices[2]],
                'breakout_direction' => $breakoutDirection
            ];
        }

        return ['detected' => false, 'confidence' => 0];
    }

    public static function checkSupportResistanceBreakout($highs, $lows, $data, $currentIndex)
    {
        if (empty($highs) || empty($lows)) {
            return ['detected' => false, 'confidence' => 0];
        }

        $currentPrice = $data[$currentIndex]['close'];

        // Get the most recent significant high and low
        $lastHigh = end($highs);
        $lastLow = end($lows);

        // Check for resistance breakout
        if ($currentPrice > $lastHigh['price'] * 1.005) {
            return [
                'detected' => true,
                'confidence' => 75,
                'levels' => [$lastHigh['price']],
                'direction' => 'bullish'
            ];
        }

        // Check for support breakdown
        if ($currentPrice < $lastLow['price'] * 0.995) {
            return [
                'detected' => true,
                'confidence' => 75,
                'levels' => [$lastLow['price']],
                'direction' => 'bearish'
            ];
        }

        return ['detected' => false, 'confidence' => 0];
    }

    public static function identifySupportResistance($highs, $lows)
    {
        $levels = [];

        // Recent resistance levels
        if (!empty($highs)) {
            $recentHighs = array_slice($highs, -3);
            foreach ($recentHighs as $high) {
                $levels[] = [
                    'level' => $high['price'],
                    'type' => 'resistance',
                    'strength' => 70
                ];
            }
        }

        // Recent support levels
        if (!empty($lows)) {
            $recentLows = array_slice($lows, -3);
            foreach ($recentLows as $low) {
                $levels[] = [
                    'level' => $low['price'],
                    'type' => 'support',
                    'strength' => 70
                ];
            }
        }

        return $levels;
    }

    public static function analyzeTrend($data, $structure, $currentIndex)
    {
        $currentPrice = $data[$currentIndex]['close'];
        $trend = 'neutral';
        $confidence = 50;
        $prediction = 'sideways';

        // Base trend analysis on detected structure
        if ($structure['type']) {
            switch ($structure['type']) {
                case 'double_top':
                    if ($structure['breakout_direction'] === 'bearish') {
                        $trend = 'bearish';
                        $confidence = 85;
                        $prediction = 'continued_decline';
                    } else {
                        $trend = 'bearish_pending';
                        $confidence = 70;
                        $prediction = 'potential_decline';
                    }
                    break;

                case 'double_bottom':
                    if ($structure['breakout_direction'] === 'bullish') {
                        $trend = 'bullish';
                        $confidence = 85;
                        $prediction = 'continued_rise';
                    } else {
                        $trend = 'bullish_pending';
                        $confidence = 70;
                        $prediction = 'potential_rise';
                    }
                    break;

                case 'head_and_shoulders':
                    if ($structure['breakout_direction'] === 'bearish') {
                        $trend = 'bearish';
                        $confidence = 90;
                        $prediction = 'strong_decline';
                    } else {
                        $trend = 'bearish_pending';
                        $confidence = 80;
                        $prediction = 'potential_decline';
                    }
                    break;

                case 'ascending_triangle':
                    if ($structure['breakout_direction'] === 'bullish') {
                        $trend = 'bullish';
                        $confidence = 80;
                        $prediction = 'continued_rise';
                    } else {
                        $trend = 'bullish_pending';
                        $confidence = 70;
                        $prediction = 'potential_breakout_up';
                    }
                    break;

                case 'descending_triangle':
                    if ($structure['breakout_direction'] === 'bearish') {
                        $trend = 'bearish';
                        $confidence = 80;
                        $prediction = 'continued_decline';
                    } else {
                        $trend = 'bearish_pending';
                        $confidence = 70;
                        $prediction = 'potential_breakout_down';
                    }
                    break;

                case 'breakout':
                    $trend = $structure['breakout_direction'] === 'bullish' ? 'bullish' : 'bearish';
                    $confidence = 75;
                    $prediction = $structure['breakout_direction'] === 'bullish' ? 'continued_rise' : 'continued_decline';
                    break;
            }
        }

        // Additional confirmation using price action
        $priceAction = self::analyzePriceAction($data, $currentIndex);

        // Adjust confidence based on price action confirmation
        if ($priceAction['direction'] === $trend) {
            $confidence = min(95, $confidence + 10);
        } elseif ($priceAction['direction'] === 'opposite') {
            $confidence = max(30, $confidence - 20);
        }

        return [
            'trend' => $trend,
            'confidence' => $confidence,
            'structure' => $structure,
            'prediction' => $prediction,
            'price_action' => $priceAction,
            'details' => self::generateTrendDetails($trend, $structure, $confidence)
        ];
    }

    public static function analyzePriceAction($data, $currentIndex)
    {
        // Simple price action analysis using recent candles
        $lookback = min(10, $currentIndex);
        $recentCandles = array_slice($data, $currentIndex - $lookback, $lookback + 1);

        $bullishCandles = 0;
        $bearishCandles = 0;

        foreach ($recentCandles as $candle) {
            if ($candle['close'] > $candle['open']) {
                $bullishCandles++;
            } else {
                $bearishCandles++;
            }
        }

        $direction = 'neutral';
        if ($bullishCandles > $bearishCandles * 1.5) {
            $direction = 'bullish';
        } elseif ($bearishCandles > $bullishCandles * 1.5) {
            $direction = 'bearish';
        }

        return [
            'direction' => $direction,
            'bullish_candles' => $bullishCandles,
            'bearish_candles' => $bearishCandles,
            'ratio' => $bullishCandles / max(1, $bearishCandles)
        ];
    }

    public static function generateTrendDetails($trend, $structure, $confidence)
    {
        $details = "Market structure: " . ($structure['type'] ?? 'No clear pattern') . ". ";
        $details .= "Current trend: " . $trend . " with " . $confidence . "% confidence. ";

        if ($structure['breakout_direction']) {
            $details .= "Breakout direction: " . $structure['breakout_direction'] . ". ";
        }

        return $details;
    }

    public static function openInternalTradeEntry($data)
    {

        DB::table('live_trades_future_results')->insert(
            [
                'orderId' => $data['orderId'],
                'symbol' => $data['symbol'],
                'side' => $data['side'],
                'amount' => $data['tradeAmount'],
                'market' => $data['market'],
                'type' => $data['open'],
                'position' => $data['position'],
                'qty' => $data['quantity'],
                'leverage' => $data['leverage'],
                'stopLoss' => $data['stopLoss'],
                'stopLossReductionPrecentage' => 0.1,
                'price' => $data['current_price'],
                'trade_status' => 'open',
                'trade_acc' => $data['trader'],
                'targetProfit' => $data['targetProfit'],
                'formula' => $data['formula'],
                'turnoverPoint' => 1,
                'liqPrice' => 1,
                'currentSupport' => 1,
                'currentResistance' => 1,
                'exchange' => 'binance',
                'created_at' => Carbon::now('Asia/Karachi'),
            ]
        );
    }

    public static function fetchLiveTrades($symbol, $startTimeUnix, $endTimeUnix, $email = 'tanveer@cryptoapis.com')
    {



        $url = "https://rocket.cryptoapis.store/master-process/handle/" . config('binance.process_manager_client_key');



        // Step 3: Create Carbon instance in Asia/Karachi timezone
        $startTime = Carbon::createFromTimestamp($startTimeUnix / 1000, 'Asia/Karachi');

        // Step 4: Clone and add 20 minutes
        $endTime = Carbon::createFromTimestamp($endTimeUnix / 1000, 'Asia/Karachi');


        $data = [
            'action' => 'FETCH_MISSING_TRADES',
            'email' => $email,
            'symbol' => $symbol,
            'start_time' => $startTime->toDateTimeString(),         // 'Y-m-d H:i:s'
            'end_time' => $endTime->toDateTimeString(),     // 'Y-m-d H:i:s'
        ];
        $response = Http::post($url, $data);

        if ($response->successful()) {
            $response = $response->json();
            return $response['data'];
        }

        return null;
    }


    public static function filterReportOnWorkerLimit($formula, $workerLimit = 5)
    {

        $newFormula = 'Filtered (' . $workerLimit . ') - ' . $formula;
        $formulaDetailsComplete = DB::table('formula_details')->where('formula', $formula)->first();

        if (!$formulaDetailsComplete) {
            return null;
        }
        $formulaDetails = json_decode($formulaDetailsComplete->report_config, true);
        $interval = $formulaDetails['interval'];
        $intervalMillis =  (CommonHelpers::$binanceIntervals[$interval] * 60 * 1000);
        $loopTimestamp = intval(($formulaDetails['startUnix'] / $intervalMillis)) * $intervalMillis;
        $allTrades = DB::table('coin_reports')
            ->where('formula', $formula)
            ->orderBy('openingTimestamp', 'ASC')
            ->get()
            ->groupBy('openingTimestamp'); // Grouped by timestamp for easy lookup

        $tradesFinalArr = [];
        $workersDetail = [];

        for ($i = 0; $i < $workerLimit; $i++) {
            $workersDetail['w' . $i] = [
                'tradeActive' => 0,
                'startTime' => null,
                'endTime' => null,
            ];
        }


        // dd($workersDetail);
        while ($loopTimestamp < $formulaDetails['endUnix']) {
            // Logic to free workers on trade completion
            foreach ($workersDetail as $workerId => $wDetail) {
                if ($wDetail['tradeActive']) {

                    if ($loopTimestamp >= $wDetail['endTime']) {
                        $workersDetail[$workerId] = [
                            'tradeActive' => 0,
                            'startTime' => null,
                            'endTime' => null,
                        ];
                    }
                }
            }
            $tradesForTimestamp = $allTrades[$loopTimestamp] ?? collect();
            // Logic to allocate trades to available workers
            if (!empty($tradesForTimestamp)) {
                foreach ($tradesForTimestamp as $tTrade) {
                    // Now allocat each trade to respective available worker
                    foreach ($workersDetail as $workerId => $wDetail) {
                        if (!$wDetail['tradeActive']) {

                            $openTime = json_decode($tTrade->buyingCandle, true)['binance_timestamp'];
                            $closeTime = json_decode($tTrade->sellingCandle, true)['binance_timestamp'];

                            $workersDetail[$workerId] = [
                                'tradeActive' => 1,
                                'startTime' => $openTime,
                                'endTime' => $closeTime,
                            ];
                            $trade = $tTrade;
                            $tradesFinalArr[] = [
                                'exchange' => $trade->exchange,
                                'symbol' => $trade->symbol,
                                'interval' => $trade->interval,
                                'market' => $trade->market,
                                'openingTimestamp' => $trade->openingTimestamp,
                                'position' => $trade->position,
                                'previousCandle' => $trade->previousCandle,
                                'buyingCandle' => $trade->buyingCandle,
                                'sellingCandle' => $trade->sellingCandle,
                                'buyingPrice' => $trade->buyingPrice,
                                'liquidationPrice' => $trade->liquidationPrice,
                                'sellingPrice' => $trade->sellingPrice,
                                'lowestPrice' => $trade->lowestPrice,
                                'lowestPricePercentage' => $trade->lowestPricePercentage,
                                'profit' => $trade->profit,
                                'closed_early' => $trade->closed_early,
                                'duration' => $trade->duration,
                                'created_at' => Carbon::now()->toDateTimeString(),
                                'formula' => $newFormula,
                                'tagName' => $trade->tagName,
                                'openingVolumes' => $trade->openingVolumes,
                                'closingVolumes' => $trade->closingVolumes,
                                'confirmCandle' => $trade->confirmCandle,
                                'highestCandle' => $trade->highestCandle,
                            ];
                            break;
                        }
                    }
                }
            }
            $loopTimestamp += $intervalMillis;
        }


        $formulaDetails['formula'] = $newFormula;

        DB::table('formula_details')->updateOrinsert(
            [
                'formula' => $newFormula,
            ],
            [
                'details' => str_replace($formula, $newFormula, $formulaDetailsComplete->details),
                'report_config' => json_encode($formulaDetails),
                'progress' => 100,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]
        );

        DB::table('coin_reports')->where('formula', $newFormula)->delete();
        DB::table('coin_reports')->insert($tradesFinalArr);

        return $tradesFinalArr;
    }


    public static function getLastGitCommit($domain = '', $repoName = '')
    {




        if (!$domain) {
            $domain = env('PLESK_GIT_DOMAIN');
        }
        if (!$repoName) {
            $repoName = env('PLESK_GIT_REPO_NAME');
        }





        // Sanitize inputs
        $domain = escapeshellarg($domain);
        $repoName = escapeshellarg($repoName);




        // Build the command
        $command = "sudo plesk ext git --get-last-commit -domain $domain -name $repoName";

        // Run the command
        $output = shell_exec($command);

        if (!$output) {
            return [
                'success' => false,
                'message' => 'No output from command or command failed.'
            ];
        }

        // Parse the output
        $lines = explode("\n", trim($output));
        $result = [
            'success' => true,
            'commit_hash' => '',
            'author' => '',
            'date' => '',
            'message' => ''
        ];

        foreach ($lines as $line) {
            if (strpos($line, 'commit ') === 0) {
                $result['commit_hash'] = trim(substr($line, 7));
            } elseif (strpos($line, 'Author:') === 0) {
                $result['author'] = trim(substr($line, 7));
            } elseif (strpos($line, 'Date:') === 0) {
                $result['date'] = trim(substr($line, 5));
            } else {
                $result['message'] .= trim($line) . " ";
            }
        }

        // Clean up message
        $result['message'] = trim($result['message']);
        return $result;
    }


    public static function deployLatestCommit($domain = '', $repoName = '')
    {
        if (!$domain) {
            $domain = env('PLESK_GIT_DOMAIN');
        }
        if (!$repoName) {
            $repoName = env('PLESK_GIT_REPO_NAME');
        }

        // Sanitize inputs
        $domain = escapeshellarg($domain);
        $repoName = escapeshellarg($repoName);

        // Build the deploy command
        $command = "sudo plesk ext git --deploy -domain $domain -name $repoName";

        // Run the command
        $output = shell_exec($command);

        if (!$output) {
            return [
                'success' => false,
                'message' => 'Deployment command returned no output or failed.'
            ];
        }

        return [
            'success' => true,
            'message' => trim($output)
        ];
    }
    /**
     * Check which intervals just closed a candle.
     * Ensures no candle is missed, even if loop is delayed.
     *
     * @return array [
     *   '1m'  => bool,
     *   '3m'  => bool,
     *   '5m'  => bool,
     *   '15m' => bool,
     *   '30m' => bool,
     *   '1h'  => bool,
     *   '4h'  => bool,
     *   '1d'  => bool
     * ]
     */
    public static function checkCandleBoundaries(): array
    {
        static $lastTriggered = [];
        $intervals = [
            '1m'  => 60,
            '3m'  => 180,
            '5m'  => 300,
            '15m' => 900,
            '30m' => 1800,
            '1h'  => 3600,
            '4h'  => 14400,
            '1d'  => 86400,
        ];

        $nowMs  = (int) round(microtime(true) * 1000); // current UTC in ms
        $nowSec = intdiv($nowMs, 1000);
        $results = [];

        foreach ($intervals as $interval => $seconds) {
            if ($interval === '4h') {
                $validHours = [1, 5, 9, 13, 17, 21];
                $current = \Carbon\Carbon::createFromTimestamp($nowSec, 'UTC');

                $latestBoundary = null;
                foreach (array_reverse($validHours) as $h) {
                    $candidate = $current->copy()->hour($h)->minute(0)->second(0);
                    if ($candidate->lessThanOrEqualTo($current)) {
                        $latestBoundary = $candidate;
                        break;
                    }
                }
                if (!$latestBoundary) {
                    $latestBoundary = $current->copy()->subDay()->hour(21)->minute(0)->second(0);
                }
                $boundaryTs = $latestBoundary->timestamp;
            } else {
                $boundaryTs = intdiv($nowSec, $seconds) * $seconds;
            }

            // 👇 Warm-up logic: if first run, initialize but don't trigger
            if (!isset($lastTriggered[$interval])) {
                $lastTriggered[$interval] = $boundaryTs;
                $results[$interval] = false;
                continue;
            }

            // Normal trigger check
            if ($lastTriggered[$interval] !== $boundaryTs && $nowMs >= ($boundaryTs * 1000 + 100)) {
                $results[$interval] = true;
                $lastTriggered[$interval] = $boundaryTs;
            } else {
                $results[$interval] = false;
            }
        }

        return $results;
    }


    public static function filterIntervalOverlapping($trades)
    {

        // Step 1: Sort by openingTimestamp ascending
        usort($trades, function ($a, $b) {
            return $a['openingTimestamp'] <=> $b['openingTimestamp'];
        });

        $filtered = [];
        $removed = [];
        $stats = [
            'total_overlaps' => 0,
            'by_month' => [] // e.g. [ 'january' => [ 'count' => 3, 'trades' => [ ... ] ] ]
        ];

        foreach ($trades as $trade) {
            $isOverlapping = false;

            foreach ($filtered as $existing) {
                // Check overlap condition:
                if (
                    $trade['openingTimestamp'] >= $existing['openingTimestamp'] &&
                    $trade['openingTimestamp'] <= $existing['closingTimestamp']
                ) {
                    // Overlapping trade found
                    $isOverlapping = true;

                    // Record overlap stats
                    $month = strtolower($trade['month']);
                    $stats['total_overlaps']++;

                    if (!isset($stats['by_month'][$month])) {
                        $stats['by_month'][$month] = [
                            'count' => 0,
                            'trades' => []
                        ];
                    }

                    $stats['by_month'][$month]['count']++;
                    $stats['by_month'][$month]['trades'][] = $trade;
                    $removed[] = $trade;
                    break;
                }
            }

            if (!$isOverlapping) {
                $filtered[] = $trade;
            }
        }

        return [
            'filteredTrades' => $filtered,
            'overlapStats' => $stats,
            'removedTrades' => $removed,
        ];
    }
}
