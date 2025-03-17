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
        $market = 'FUTURE'
    ) {


        $tradesTotal = [];
        $coins = DB::table('coins')->where('market', $market)->get();

        foreach ($coins as $coin) {

            $targetProfit = 0.5;

            try {
                $symbol = $coin->symbol;
                $data = BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');

                $trades = self::processCandles($symbol, '5m', 'FUTURE', $data, $targetProfit);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', $interval)->where('market', $market)->where('position', 'SHORT')->delete();
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
        $market = 'FUTURE'
    ) {

        $tradesTotal = [];
        $targetProfit = 2;
        try {
            $data = BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');

            $trades = self::processCandles($symbol, '5m', 'FUTURE', $data, $targetProfit);
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
    protected static function processCandles($symbol, $interval, $market, $data, $targetProfit)
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

                $volumeCrossover = false;
                $loopIndex = $index;

                while (true) {

                    if ($data[$loopIndex]['volumeMA5'] < $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] > $data[$loopIndex - 1]['volumeMA10']) {
                        $volumeCrossover = true;
                        break;
                    }
                    if ($data[$loopIndex]['volumeMA5'] > $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] < $data[$loopIndex - 1]['volumeMA10']) {
                        break;
                    }
                    $loopIndex--;
                }


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
                $isDownwardWick = $data[$index]['close'] < $data[$index]['open'] && $lowerWick < $upperWick * 2;


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


                if ($index > $obvCandles) {

                    if (
                        // Current and previous MACD should be green
                        $data[$index]['histogram'] > 0
                        && $isDownwardWick
                        && ($kdjCrossover || $kdjApproachingCrossover)
                        && $totalGreenCandles > 4
                        && $data[$index]['per'] <= -0.2
                        && $data[$index]['per'] > -0.6
                        && $data[$index]['close'] < $lastHighest * (1 - 0.7 / 100)
                        && $data[$index]['avl'] < $data[$index - 1]['avl']
                        && $data[$index]['dif'] < $data[$index - 1]['dif']
                        && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] - 10
                    ) {
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
                    $currentTrade['formula'] = 'MacdSwing';
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
