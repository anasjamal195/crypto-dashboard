<?php



namespace App\Services;

use DateTime;
use Carbon\Carbon;

use App\Services\BinanceApiService;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketTrendService
{
    private static $timestampFormat = 'Y-m-d H:i:s';
    private static $mysqlDateTimeFormat = 'Y-m-d H:i:s';
    private static $coins = ['BTCUSDT', 'ETHUSDT'];
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function dumpMarketTrends(
        $interval = '1m',
        $limit = 15,
        $market = 'SPOT',
        $timestamp = null,
    ) {


        foreach (self::$coins as $coin) {


            try {
                $symbol = $coin;


                $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
                $previousThreshold = $data[$limit - 1]['close'];
                foreach ($data as $candle) {
                    if ($candle['close'] < $previousThreshold)
                        $previousThreshold = $candle['close'];
                }
                $data[$limit - 1]['timestamp'] = $data[$limit - 1]['timestamp'] / 1000;
                $date = new \DateTime("@{$data[$limit - 1]['timestamp']}");
                $date->setTimezone(new \DateTimeZone('Asia/Karachi'));


                if ($data[$limit - 1]['close'] < 1.006 * $previousThreshold) {
                    if (DB::table('market_trends')->where('market', $market)->where('symbol', $symbol)->where('interval', $interval)->first()) {
                        DB::table('market_trends')->where('market', $market)->where('symbol', $symbol)->where('interval', $interval)->update(
                            [
                                'symbol' => $symbol,
                                'interval' => $interval,
                                'market' => $market,
                                'signal' => 'red',
                                'tradeType' => 'buyLong',

                            ]
                        );
                    } else {
                        DB::table('market_trends')->insert([
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'market' => $market,
                            'signal' => 'red',
                            'tradeType' => 'buyLong',

                        ]);
                    }
                } else {
                    if (DB::table('market_trends')->where('market', $market)->where('symbol', $symbol)->where('interval', $interval)->first()) {
                        DB::table('market_trends')->where('market', $market)->where('symbol', $symbol)->where('interval', $interval)->update(
                            [
                                'symbol' => $symbol,
                                'interval' => $interval,
                                'market' => $market,
                                'tradeType' => 'buyLong',
                                'signal' => 'green'
                            ]
                        );
                    } else {
                        DB::table('market_trends')->insert([
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'market' => $market,
                            'tradeType' => 'buyLong',
                            'signal' => 'green'
                        ]);
                    }
                }
            } catch (\Throwable $th) {
                Log::error('DataDumper: Error - ' . $th->getMessage());
                Log::error($th->getTraceAsString());
            }
        }
        return true;
    }
    public static function getHistoricalTrends(
        $interval = '1m',
        $market = 'SPOT',
        $timestamp = null,
    ) {

        $marketTrendData = [];

        foreach (self::$coins as $coin) {


            try {
                $symbol = $coin;

                $limit = 1000;
                $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
                foreach ($data as $indexCandle => &$candle) {
                    $candle['marketTrend'] = '';

                    $candle['timestamp'] = $candle['timestamp'] / 1000;
                    $date = new \DateTime("@{$candle['timestamp']}");
                    $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
                    $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
                    if ($indexCandle < 15) {
                        continue;
                    }


                    $previousThreshold = $candle['close'];

                    // Calculating lowest price in previous 15 candles
                    for ($i = $indexCandle - 15; $i <= $indexCandle; $i++) {
                        if ($previousThreshold > $data[$i]['close']) {
                            $previousThreshold = $data[$i]['close'];
                        }
                    }



                    if ($candle['close'] < 1.006 * $previousThreshold) {
                        // Red Signal
                        $candle['marketTrend'] = 'red';
                    } else {
                        $candle['marketTrend'] = 'green';
                    }
                }

                $marketTrendData[$coin] = $data;
            } catch (\Throwable $th) {
                Log::error('DataDumper: Error - ' . $th->getMessage());
                Log::error($th->getTraceAsString());
            }
        }
        return $marketTrendData;
    }

    // Using indivisual Coin Data on a single Interval
    public static function getSymbolHistoricalTrendsSet1(
        $symbol,
        $interval = '1m',
        $market = 'SPOT',
        $timestamp = null,
    ) {


        try {

            $limit = 1000;
            $data_interval = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $last_timestamp_1m = $data_interval[0]['binance_timestamp'];
            $data_15m = BinanceApiService::getCandleStickData($symbol, '15m', $limit, $last_timestamp_1m - (15 * 60 * 1000), $market);

            foreach ($data_interval as $index => &$candle_1m) {
                $timestamp_1m = $candle_1m['binance_timestamp'] / 1000;
                $candle_1m['nearest_15m_candle'] = null;
                $timestamp_nearest_15m = $timestamp_1m * 1000;
                if ($timestamp_1m % (15 * 60) != 0)
                    $timestamp_nearest_15m = (floor($timestamp_1m / (15 * 60)) * (15 * 60)) * 1000;
                foreach ($data_15m as $candle_15m) {
                    if ($candle_15m['binance_timestamp'] == $timestamp_nearest_15m) {
                        $candle_1m['nearest_15m_candle'] = $candle_15m;
                        $candle_1m['marketTrend'] = 'red';
                        if ($index > 100) {
                            if ($candle_1m['ma7'] > $candle_1m['ma25']  &&  $candle_1m['ma7'] > $candle_1m['ma99']) {
                                $candle_1m['marketTrend'] = 'green';
                            }
                        }
                    }
                }
            }
            return $data_interval;
        } catch (\Throwable $th) {
            Log::error('DataDumper: Error - ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            return $th;
        }
    }


    // Using indivisual Coin Data on a single Interval
    public static function getSymbolHistoricalTrendsSet2(
        $symbol,
        $interval = '1m',
        $market = 'SPOT',
        $timestamp = null,
    ) {


        try {

            $limit = 1000;
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $signal = 'green';
            $indexCounter = 0;
            $indexLimit = 6;
            foreach ($data as $index => &$candle) {

                if ($index < 100) {
                    $candle['marketTrend'] = 'red';
                    continue;
                }

                if ($indexCounter >= $indexLimit) {
                    $signal = 'green';
                    $indexCounter = 0;
                    $candle['marketTrend'] = 'green';
                }
                $candle['marketTrend'] = 'green';
                if ($signal = 'red' && $candle['ma7'] <= $data[$index - 1]['ma7']) {
                    $candle['marketTrend'] = 'red';
                    $indexCounter++;
                    continue;
                } else if ($signal = 'red' && $candle['ma7'] > $data[$index - 1]['ma7']) {
                    $candle['marketTrend'] = 'green';
                    $signal = 'green';
                    $indexCounter = 0;
                } else if ($candle['ma7'] <= $candle['ma99'] && $data[$index - 1]['ma7'] > $data[$index - 1]['ma99'] && $candle['rsi6'] > 70) {
                    $signal = 'red';
                    continue;
                }
            }
            return $data;
        } catch (\Throwable $th) {
            Log::error('DataDumper: Error - ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            dd($th);
            return $th;
        }
    }
    public static function getCurrentSupportResistanceGraph(
        $symbol = 'BTCUSDT',
        $interval = '1m',
        $market = 'SPOT',
        $candleSpan = 10,
        $timestamp = null,
    ){
        try {
            $limit = 100;
            
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $lastSupport = null;
            $lastResistance = null;
            $supportIndex = 0;
            $resistanceIndex = 0;
            foreach($data as &$candle){
                $candle['marketTrend'] = 'blue';
            }
            for($i = $limit - 1;$i >= 0;$i--){
                $resistance = self::calculatePivotHighAtIndex($data,$i,$candleSpan,$candleSpan);
                $support = self::calculatePivotLowAtIndex($data,$i,$candleSpan,$candleSpan);
                if ($supportIndex != 0 && $resistanceIndex != 0){
                    break;
                }
                if($support && $supportIndex == 0){
                    $supportIndex = $i;
                    $lastSupport = $support;
                }
                if($resistance && $resistanceIndex == 0){
                    $resistanceIndex = $i;
                    $lastResistance = $resistance;
                }
               
            }

            // dd($lastSupport,$lastResistance);
            for($i = min($supportIndex,$resistanceIndex); $i < $limit; $i++){
                $data[$i]['support'] = $lastSupport;
                $data[$i]['resistance'] = $lastResistance;
                
            }
            
    
           
            return $data;
        } catch (\Throwable $th) {
            Log::error('DataDumper: Error - ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            throw $th;
        }
    }


    public static function getCurrentSupportResistanceValue(
        $symbol = 'BTCUSDT',
        $interval = '1m',
        $market = 'SPOT',
        $candleSpan = [10],
        $timestamp = null,
    ){
        try {
            $limit = 100;
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $supportResistances = [];
            foreach($candleSpan as $span){
                $lastSupport = null;
                $lastResistance = null;
                $supportIndex = 0;
                $resistanceIndex = 0;
                for($i = $limit - 1;$i >= 0;$i--){
                    $resistance = self::calculatePivotHighAtIndex($data,$i,$span,$span);
                    $support = self::calculatePivotLowAtIndex($data,$i,$span,$span);
                    if ($supportIndex != 0 && $resistanceIndex != 0){
                        break;
                    }
                    if($support && $supportIndex == 0){
                        $supportIndex = $i;
                        $lastSupport = $support;
                    }
                    if($resistance && $resistanceIndex == 0){
                        $resistanceIndex = $i;
                        $lastResistance = $resistance;
                    }
                   
                }
    
                $supportResistances[$span] = [
                    'support' => $lastSupport,    
                    'resistance' => $lastResistance,    
                    'supportCandle' => $data[$supportIndex],    
                    'resistanceCandle' => $data[$resistanceIndex],    
                ];
            }
            return $supportResistances;
           
        } catch (\Throwable $th) {
            Log::error('DataDumper: Error - ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            throw $th;
        }
    }
    public static function getSymbolHistoricalTrendsSet3(
        $symbol = 'BTCUSDT',
        $interval = '1m',
        $market = 'SPOT',
        $candleSpan = 10,
        $timestamp = null,
    ) {
        try {
            $limit = 500;
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $candleSpan = 10;
            // Initialize variables to track last support and resistance levels
            $lastSupport = null;
            $lastResistance = null;
    
            // Loop through the candles to calculate support and resistance
            foreach ($data as $index => &$candle) {
                $close = $candle['close'];
                $open = $candle['open'];
                $high = $candle['high'];
                $low = $candle['low'];
    
                // Default values for support and resistance
                $candle['support'] = null;
                $candle['resistance'] = null;
                $candle['marketTrend'] = 'blue'; // Default trend

    
                // Check if we have enough data to calculate pivot points
                if ($index >= $candleSpan && $index + $candleSpan < count($data)) {
                    // Use the pivot functions to calculate pivot high and low
                    $pivotHigh = self::calculatePivotHighAtIndex($data, $index, $candleSpan, 1);
                    $pivotLow = self::calculatePivotLowAtIndex($data, $index, $candleSpan, 1);
    
                    // Update support and resistance levels only if valid pivots are found
                    if ($pivotHigh !== null) {
                        $lastResistance = $pivotHigh;
                    }
                    
                    if ($pivotLow !== null) {
                        $lastSupport = $pivotLow;
                    }
                }
    
                // Assign the last known support and resistance levels to the current candle
                $candle['support'] = $lastSupport;
                $candle['resistance'] = $lastResistance;
    
                // Detect trend based on breaking of support/resistance
                if ($lastResistance !== null && $close > $lastResistance) {
                    // Resistance is broken, reset resistance level
                    $lastResistance = null;
                    $candle['marketTrend'] = 'green'; // Uptrend
                } elseif ($lastSupport !== null && $close < $lastSupport) {
                    // Support is broken, reset support level
                    $lastSupport = null;
                    $candle['marketTrend'] = 'red'; // Downtrend
                } else {
                    // No breakout, maintain the current trend
                    $candle['marketTrend'] = 'blue';
                }
            }
    
            return $data;
        } catch (\Throwable $th) {
            Log::error('DataDumper: Error - ' . $th->getMessage());
            Log::error($th->getTraceAsString());
            throw $th;
        }
    }


    /**
     * Function to calculate pivot high for a specific candle index
     *
     * @param array $data The array of candles (each candle has 'high', 'low', etc.)
     * @param int $index The index of the candle to calculate the pivot high for
     * @param int $leftBars Number of candles to the left to compare
     * @param int $rightBars Number of candles to the right to compare
     * @return float|null The pivot high value, or null if no pivot is found
     */
    private static function calculatePivotHighAtIndex(array $data, int $index, int $leftBars, int $rightBars): ?float
    {
        $length = count($data);

        // Check if the index is within bounds
        if ($index < $leftBars || $index + $rightBars >= $length) {
            return null; // Not enough candles to calculate pivot
        }

        $currentHigh = $data[$index]['high'];
        $isPivotHigh = true;

        // Check candles to the left
        for ($j = 1; $j <= $leftBars; $j++) {
            if ($currentHigh <= $data[$index - $j]['high']) {
                $isPivotHigh = false;
                break;
            }
        }

        // Check candles to the right
        if ($isPivotHigh) {
            for ($j = 1; $j <= $rightBars; $j++) {
                if ($currentHigh <= $data[$index + $j]['high']) {
                    $isPivotHigh = false;
                    break;
                }
            }
        }

        return $isPivotHigh ? $currentHigh : null;
    }

    /**
     * Function to calculate pivot low for a specific candle index
     *
     * @param array $data The array of candles (each candle has 'high', 'low', etc.)
     * @param int $index The index of the candle to calculate the pivot low for
     * @param int $leftBars Number of candles to the left to compare
     * @param int $rightBars Number of candles to the right to compare
     * @return float|null The pivot low value, or null if no pivot is found
     */
    private static function calculatePivotLowAtIndex(array $data, int $index, int $leftBars, int $rightBars): ?float
    {
        $length = count($data);

        // Check if the index is within bounds
        if ($index < $leftBars || $index + $rightBars >= $length) {
            return null; // Not enough candles to calculate pivot
        }

        $currentLow = $data[$index]['low'];
        $isPivotLow = true;

        // Check candles to the left
        for ($j = 1; $j <= $leftBars; $j++) {
            if ($currentLow >= $data[$index - $j]['low']) {
                $isPivotLow = false;
                break;
            }
        }

        // Check candles to the right
        if ($isPivotLow) {
            for ($j = 1; $j <= $rightBars; $j++) {
                if ($currentLow >= $data[$index + $j]['low']) {
                    $isPivotLow = false;
                    break;
                }
            }
        }

        return $isPivotLow ? $currentLow : null;
    }
    public static function istradeAllowed(
        $interval = '1m',
        $limit = 15,
        $market = 'SPOT',
        $timestamp = null,
    ) {

        $trend = true;

        foreach (self::$coins as $coin) {


            try {
                $symbol = $coin;

                $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
                $previousThreshold = $data[$limit - 1]['close'];
                foreach ($data as $candle) {
                    if ($candle['close'] < $previousThreshold)
                        $previousThreshold = $candle['close'];
                }
                $data[$limit - 1]['timestamp'] = $data[$limit - 1]['timestamp'] / 1000;
                $date = new \DateTime("@{$data[$limit - 1]['timestamp']}");
                $date->setTimezone(new \DateTimeZone('Asia/Karachi'));


                if ($data[$limit - 1]['close'] < 1.006 * $previousThreshold) {
                    $trend = false;
                }
            } catch (\Throwable $th) {
                Log::error('DataDumper: Error - ' . $th->getMessage());
                Log::error($th->getTraceAsString());
            }
        }
        return $trend;
    }
}
