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
