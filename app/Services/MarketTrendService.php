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

    public static function getSymbolHistoricalTrendsSet3(
        $symbol = 'BTCUSDT',
        $interval = '1m',
        $market = 'SPOT',
        $candleSpan = 12,
        $timestamp = null,
    ) {


        try {

          
            $limit = 1000;
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, $timestamp, $market);
            $signal = 'green';
            $indexCounter = 0;
            $indexLimit = 6;

            $supportPrice = $data[0]['low'];
            $supportIndex = 0;

            $breakPointIndex = 0;
           

            $tradePrice = 0;
            $targetProfit = 0.4;
            $tradeType = '';


            $lastTrade = '';
            $resistancePrice = $data[0]['high'];
            $resistanceIndex = 0;
            $lastIndex = 0;

            $waitingIndex = $candleSpan;

            $candlesCounter = 0;
            $calculateSportResistance = true;
            $supportThreshold = $resistanceThreshold  = 0.3;

            foreach ($data as $index => &$candle) {
                $candle['marketTrend'] = 'blue';
                /*
                calculate sport (min) and resistance(max) of last 100 candles,
                wait for resistance to break after that,
                when resistance breaks,
                skip 100 next candles
                */


                if ($waitingIndex) {
                    $waitingIndex--;
                    continue;
                }

                // // Handle if a trade is open
                if ($tradePrice !== 0) {
                    if ($tradeType == "LONG") {
                        $candle['marketTrend'] = 'yellow';

                        // dd($tradePrice,$tradePrice * (1 + $targetProfit/100));
                        if ($candle['close'] >= $tradePrice * (1 + $targetProfit / 100)) {

                            // dd($candle['close'],$tradePrice,$resistancePrice);
                            $tradePrice = 0;
                            $tradeType = '';
                            $lastTrade = 'RESISTANCE';
                            // $latestIndex = max($resistanceIndex,$supportIndex);
                            // $waitingIndex = $index - $lastIndex > 50? 1:50 - ($index - $lastIndex);
                            $waitingIndex = $candleSpan;
                            $calculateSportResistance = true;
                            // $waitingIndex = 1000;
                            continue;
                        }
                    }
                    if ($tradeType == 'SHORT') {
                        $candle['marketTrend'] = 'white';

                        if ($candle['close'] <= $tradePrice * (1 - $targetProfit / 100)) {
                            // dd($tradePrice,$candle['close']);
                            $tradePrice = 0;
                            $tradeType = '';
                            $lastTrade = 'SUPPORT';

                            // $latestIndex = max($resistanceIndex,$supportIndex);
                            // $waitingIndex = $index - $lastIndex > 50? 1:50 - ($index - $lastIndex);
                            $waitingIndex = $candleSpan;
                            $calculateSportResistance = true;
                            continue;
                        }
                    }
                    continue;
                }


                if ($calculateSportResistance) {
                    $supportIndex = $resistanceIndex = $index;
                    $supportPrice = $candle['close'];
                    $resistancePrice = $candle['close'];
                    // $data[$index - 50]['marketTrend'] = 'orange';
                    // Calculate nEW values for Support and Resistance
                    for ($i = $index - $candleSpan; $i < $index; $i++) {
                        if ($data[$i]['close'] < $supportPrice) {
                            $supportPrice = $data[$i]['close'];
                            $supportIndex = $i;
                        }

                        if ($data[$i]['close'] > $resistancePrice) {
                            $resistancePrice = $data[$i]['close'];
                            $resistanceIndex = $i;
                        }
                    }

                    $data[$supportIndex]['marketTrend'] = 'red';
                    // $data[$supportIndex]['close'] = $data[$supportIndex]['low'];
                    $data[$resistanceIndex]['marketTrend'] = 'green';
                    // $data[$resistanceIndex]['close'] = $data[$resistanceIndex]['high'];
                    // $calculateSportResistance = false;
                    $waitingIndex = $candleSpan;
                    continue;
                }



                // Now check for resistance/Spot break
                if (!$calculateSportResistance) {
                    if ($data[$index]['close'] > $resistancePrice * (1 + $resistanceThreshold / 100)) {
                        // Resistance Broken,
                        // dd("calculating",$index);
                        $candle['marketTrend'] = 'yellow';
                        $tradePrice = $candle['close'];
                        $tradeType = 'LONG';
                    } else if ($data[$index]['close'] < $supportPrice * (1 - $supportThreshold / 100)) {
                        // Resistance Broken,
                        $candle['marketTrend'] = 'white';

                        // Initiate a LONG Trade
                        $tradePrice = $candle['close'];
                        $tradeType = 'SHORT';
                    }
                }
            }
            // dd($data);
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
