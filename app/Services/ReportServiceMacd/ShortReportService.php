<?php

namespace App\Services\ReportServiceMacd;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShortReportService
{
    /**
     * Updates the coin report data by fetching the latest from the Binance API.
     * 
     * @param string $interval The time interval for candlestick data.
     * @param int $limit The number of candlesticks to retrieve.
     * @param float $rsiThreshold RSI threshold for buy triggers.
     * @param int $obvCandles The number of candles to consider for OBV calculation.
     * @param float $obvLimit OBV threshold percentage.
     * @param int $stochDLimit Stochastic D threshold.
     * @param float $targetProfit The target profit percentage to sell.
     * @param int $maxChange Maximum price change percentage for filtering stable coins.
     * @param int $minChange Minimum price change percentage for filtering stable coins.
     * @param int $stableCoinLimit Number of stable coins to fetch.
     */
    public static function updateCoinReport(
        $interval = '1m',
        $limit = 1000,
        $market = 'FUTURE',
        $formula = 'Default',
    ) {


        $tradesTotal = [];
        $coins = DB::table('coins')->where('market', $market)->get();

        foreach ($coins as $coin) {

            $targetProfit = 0.5;

            try {
                $symbol = $coin->symbol;
                $data = BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');

                $trades = self::processCandles($symbol, '5m', 'FUTURE', $data, $targetProfit, $formula);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', $interval)->where('formula', $formula)->where('market', $market)->where('position', 'SHORT')->delete();
                DB::table('coin_reports')->insert($trades);
                $tradesTotal[$symbol] = $trades;

                // Log::info("Updated coin report for $symbol at interval $interval.");
            } catch (\Exception $e) {
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(10);
        }
        return $tradesTotal;
    }
    public static function getCoinReport(
        $symbol,
        $interval = '1m',
        $limit = 1000,
        $market = 'FUTURE',
        $formula = 'Default',
    ) {

        $tradesTotal = [];
        $targetProfit = 1;
        try {
            $data = BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');

            $trades = self::processCandles($symbol, '5m', 'FUTURE', $data, $targetProfit, $formula);
            $tradesTotal[$symbol] = $trades;
        } catch (\Exception $e) {
            Log::error("Failed to update coin reports: " . $e->getMessage());
            dd($e);
        }
        usleep(100000); // 100ms Sleep after each iteration

        return $tradesTotal;
    }
    /**
     * Processes candlestick data to determine trade opportunities based on technical indicators.
     *
     * @param array $data Array of candlestick data.
     * @param float $rsiThreshold RSI threshold for determining buy signals.
     * @param int $obvCandles Number of candles to use for OBV high calculation.
     * @param float $obvLimit OBV limit for buy signal.
     * @param int $stochDLimit Stochastic D limit for buy signal.
     * @param float $targetProfit Target profit percentage for sell signal.
     * @return array Processed trade data.
     */
    protected static function processCandles($symbol, $interval, $market, $data, $targetProfit, $formula)
    {
        $buy_price = 0;
        $buy_triggers = [];
        $priceLock = $data[0]['close'];
        $priceLockIndex = 0;
        $skipIndex = 0;
        $counter = 0;
        $currentTrade = [];
        $trades = [];
        $lockedPriceBuy = 0;
        $lowestPrice = 0;
        $buyingCandles = [];
        $timestamp = $data[0]['binance_timestamp'] - (60 * 5000 * 1000);
        $averageAdjustmetCandles =  BinanceApiService::getCandleStickData($symbol, $interval, 1000, $timestamp, $market);

        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($averageAdjustmetCandles, $data));


        foreach ($data as $index => $candle) {

            // Skip First 1000 Candles
            if ($index < 1000) {
                continue;
            }


            $obvCandles = 15;
            $idealBuying = IdealTradeService::getIdealOpeningCandlesShort(array_slice($data, $index - 1000, 1000));
            // dd($symbol,$index,$idealBuying);
            if (empty($idealBuying))
                continue;
            $averages = IdealTradeService::getAverages($idealBuying, 'SHORT');

            $rsiThreshold = $averages['rsi6'];
            $stochDLimit =  $averages['stoch_d'];
            $obvLimit = $averages['previousObvLow'] ? (($averages['previousObvLow'] - $averages['obv']) / $averages['previousObvLow']) * 100 : 0;
            $supportResistanceData = array_slice($data, $index - 300, 300);
            $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($supportResistanceData, [7]);

            $newResistance = $supportResistance[7]['resistance'] * (1 - 0.5 / 100);


            if ($buy_price == 0) {

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


                $maCondition = abs($data[$index]['ma7'] - $data[$index]['ma25']) < abs($data[$index - 1]['ma7'] - $data[$index - 1]['ma25']);
                if ($index > $obvCandles) {

                    if (
                        // Current and previous MACD should be green
                        $data[$index]['histogram'] > 0
                        && $isUpwardWick
                        && ($kdjCrossover || $kdjApproachingCrossover)
                        && $totalGreenCandles > 4
                        && $data[$index]['per'] <= -0.2
                        && $data[$index]['per'] > -0.6
                        && $data[$index]['close'] < $lastHighest * (1 - 0.7 / 100)
                        && $data[$index]['avl'] < $data[$index - 1]['avl']
                        && $data[$index]['dif'] < $data[$index - 1]['dif']
                        && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] - 10
                        && $data[$index]['per'] < 0 && $data[$index - 1]['per'] > 0
                        && $maCondition
                        && !$difDeaCondition
                    ) {







                        // New Conditions on 2h 
                        // Fetch 2-hour candlestick data
                        $data2h = BinanceApiService::getCandleStickDataPast($symbol, '2h', 100, $candle['binance_timestamp'], 'FUTURE');
                        $candle2h = end($data2h);
                        $secondLastCandle2h = prev($data2h);
                        $thirdLastCandle2h = prev($data2h);

                        // $instantOpen = false;

                        // // Condition 6: Skip trade if MA7 is above both MA25 and MA99, and previous candle's percentage change is non-positive
                        // if ($candle2h['ma7'] < $candle2h['ma25'] && $candle2h['ma7'] < $candle2h['ma99'] && $secondLastCandle2h['per'] >= 0) {
                        //     continue;
                        // }

                        // // Condition 5: Check for instant opening
                        // if (
                        //     ($candle2h['ma7'] < $candle2h['ma25'] && $secondLastCandle2h['ma7'] > $secondLastCandle2h['ma25']) ||
                        //     ($candle2h['ma7'] < $candle2h['ma99'] && $secondLastCandle2h['ma7'] > $secondLastCandle2h['ma99'])
                        // ) {
                        //     $instantOpen = true;
                        // }

                        // Calculate wick and solid region sizes
                        $upperWick = $secondLastCandle2h['high'] - max($secondLastCandle2h['close'], $secondLastCandle2h['open']);
                        $lowerWick = min($secondLastCandle2h['close'], $secondLastCandle2h['open']) - $secondLastCandle2h['low'];
                        $solidRegion = abs($secondLastCandle2h['close'] - $secondLastCandle2h['open']);

                        // // Skip trade if it's not an instant opening and doesn't meet Conditions 1, 3, or 4
                        // if (
                        //     !$instantOpen &&
                        //     !(
                        //         $secondLastCandle2h['per'] <= 0.15 || // Condition 1
                        //         ($secondLastCandle2h['per'] > 0 && $lowerWick < $upperWick && $lowerWick < $solidRegion * 0.1) || // Condition 3
                        //         ($lowerWick == 0 && $upperWick > 0) // Condition 4
                        //     )
                        // ) {
                        //     continue;
                        // }

                        // // Condition 2: Final check - skip trade if percentage change is positive and upper wick is greater than lower wick
                        // if ($secondLastCandle2h['per'] < 0 && $upperWick < $lowerWick) {
                        //     continue;
                        // }


                        // Custom Condition
                        if (
                            !(

                                $secondLastCandle2h['histogram'] < $thirdLastCandle2h['histogram']
                            )
                        ) {
                            continue;
                        }
                        // dd($candle, $secondLastCandle2h, $thirdLastCandle2h, $symbol);


                        $data15m = BinanceApiService::getCandleStickDataPast($symbol, '15m', 100, $candle['binance_timestamp'], 'FUTURE');
                        $candle15m = end($data15m);
                        $secondLastCandle15m = prev($data15m);
                        $thirdLastCandle15m = prev($data15m);




                        if (

                            $secondLastCandle15m['histogram'] > $thirdLastCandle15m['histogram'] && $secondLastCandle15m['histogram'] < 0

                        ) {
                            continue;
                        }


                        $data1h = BinanceApiService::getCandleStickDataPast($symbol, '1h', 100, $candle['binance_timestamp'], 'FUTURE');
                        $candle1h = end($data1h);
                        $secondLastCandle1h = prev($data1h);
                        $thirdLastCandle1h = prev($data1h);
                        $fourthLastCandle1h = prev($data1h);
                        $fifthLastCandle1h = prev($data1h);


                        if (
                            (
                                $secondLastCandle1h['per'] > 0
                                && $thirdLastCandle1h['per'] > 0
                                && $fourthLastCandle1h['per'] > 0
                                // && $fifthLastCandle1h['per'] > 0
                            )
                        ) {
                            continue;
                        }

                        $data4h = BinanceApiService::getCandleStickDataPast($symbol, '4h', 100, $candle['binance_timestamp'], 'FUTURE');
                        $candle4h = end($data4h);
                        $secondLastCandle4h = prev($data4h);
                        $thirdLastCandle4h = prev($data4h);
                        $fourthLastCandle4h = prev($data4h);
                        $fifthLastCandle4h = prev($data4h);

                        if(
                            $candle4h['per'] < -0.25
                        ){
                            continue;
                        }

                        
                        $candle['should_buy'] = true;
                        $candle['previousObvHigh'] = 0;
                        $candle['previousObvHighReduced'] = 0;
                        $buy_price = $candle['close'];
                        $buy_triggers[] = $candle;
                        $currentTrade['buyingCandle'] = json_encode($candle);
                        $currentTrade['buyingAverages'] = json_encode($averages);
                        $lowestPrice = $buy_price;
                    }
                }
            } else {
                if ($lowestPrice < $candle['high'])
                    $lowestPrice = $candle['high'];
                if ($candle['low'] <= $buy_price * (1 - $targetProfit / 100)) {
                    $liquidationPrice = BinanceApiService::calculateLiquidationPrice($symbol, $buy_price, CommonHelpers::getSettingsValue('future_coin_report_leverage', 10), 'short');
                    $candle['should_sell'] = true;
                    $buy_triggers[] = $candle;
                    $currentTrade['sellingCandle'] = json_encode($candle);
                    $currentTrade['buyingPrice'] = $buy_price;
                    $currentTrade['market'] = $market;
                    $currentTrade['sellingPrice'] = $candle['low'];
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = $interval;
                    $currentTrade['profit'] = abs(round(($candle['low'] - $buy_price) / $buy_price * 100, 2));
                    $currentTrade['lowestPrice'] = $lowestPrice;
                    $currentTrade['liquidationPrice'] = $liquidationPrice;
                    $currentTrade['lowestPricePercentage'] = abs((($buy_price - $lowestPrice) / $buy_price)) * 100;
                    $currentTrade['position'] = 'SHORT';
                    $currentTrade['formula'] = $formula;
                    $lowestPrice = 0;
                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);
                    $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;
                    $trades[] = $currentTrade;
                    $currentTrade = [];
                    $buy_price = 0;
                }
            }
        }


        // For shifting indexes
        $data_new = [];
        foreach ($data as $d) {
            $data_new[] = $d;
        }
        // dd($data_new);
        $data = $data_new;

        return $trades;
    }
}
