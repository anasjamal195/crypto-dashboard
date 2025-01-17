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
