<?php

namespace App\Services;

use App\CommonHelpers;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoinReportService
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

        $marketTrends = MarketTrendService::getHistoricalTrends($interval, $market);
        $tradesTotal = [];
        $coins = DB::table('coins')->where('market', $market)->get();

        foreach ($coins as $coin) {

            $targetProfit = 0.5;

            try {
                $symbol = $coin->symbol;
                $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, $market);

                $trades = self::processCandles($symbol, $interval, $market, $data, $targetProfit, $marketTrends);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', $interval)->where('market', $market)->delete();
                DB::table('coin_reports')->insert($trades);
                $tradesTotal[$symbol] = $trades;

                Log::info("Updated coin report for $symbol at interval $interval.");
            } catch (\Exception $e) {
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            usleep(100000); // 100ms Sleep after each iteration
        }
        return $tradesTotal;
    }
    public static function getCoinReport(
        $symbol,
        $interval = '1m',
        $limit = 1000,
        $market = 'FUTURE'
    ) {

        $marketTrends = MarketTrendService::getHistoricalTrends($interval, $market);
        $tradesTotal = [];
        $targetProfit = 0.4;
        try {
            $data = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, $market);
            $trades = self::processCandles($symbol, $interval, $market, $data, $targetProfit, $marketTrends);
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
    protected static function processCandles($symbol, $interval, $market, $data, $targetProfit, $marketTrends)
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
        $timestamp = $data[0]['binance_timestamp'] - (60 * 1000 * 1000);
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
            $idealBuying = IdealTradeService::getIdealBuyingCandles(array_slice($data, $index - 1000, 1000));
            // dd($symbol,$index,$idealBuying);
            if (empty($idealBuying))
                continue;
            $averages = IdealTradeService::getAverages($idealBuying);


            $rsiThreshold = $averages['rsi6'];
            $stochDLimit = $averages['stoch_rsi'] * 2;
            $obvLimit = $averages['previousObvHigh'] ? (($averages['previousObvHigh'] - $averages['obv']) / $averages['previousObvHigh']) * 100 : 100;
            if ($buy_price == 0) {
                if ($candle['rsi6'] < $rsiThreshold && ($candle['ma7'] < $candle['ma25'] && $candle['ma25'] < $candle['ma99'])) {

                    if ($index > $obvCandles) {
                        $previousHighObv = $candle['obv'];
                        for ($i = $index - $obvCandles; $i <= $index; $i++) {
                            if ($data[$i]['obv'] > $previousHighObv) {
                                $previousHighObv = $data[$i]['obv'];
                            }
                        }

                        $previousStochHigh = $candle['stoch_d'];
                        for ($i = $index - 5; $i <= $index; $i++) {
                            if ($data[$i]['stoch_d'] > $previousStochHigh) {
                                $previousStochHigh = $data[$i]['stoch_d'];
                            }
                        }
                        // $stochCondition =   ($candle['stoch_d'] <= ($previousStochHigh * (1 - $stochDLimit / 100)));
                        $stochCondition =   ($candle['stoch_d'] <=  $stochDLimit);
                        $obvCondition = ($candle['obv'] <= ($previousHighObv * (1 - $obvLimit / 100)));
                        // $obvCondition = true;
                        // $wrCondition  = ($candle['wr'] <= $wrLimit);
                        $wrCondition  = true;
                        // $stochDiff = abs($candle['stoch_d'] - $candle['stoch_k']) < 0.5;

                        $obvPositiveCondition = true;
                        if($candle['obv'] > 0 && $candle['rsi6'] > 0){
                            $obvPositiveCondition = false;
                        }

                        if ($obvCondition && $stochCondition && $wrCondition && $obvPositiveCondition) {
                            $candle['should_buy'] = true;
                            $candle['previousObvHigh'] = $previousHighObv;
                            $candle['previousObvHighReduced'] = $previousHighObv * (1 - $obvLimit / 100);
                            $buy_price = $candle['close'];
                            $buy_triggers[] = $candle;
                            $currentTrade['buyingCandle'] = json_encode($candle);
                            $currentTrade['buyingAverages'] = json_encode($averages);
                            $lowestPrice = $buy_price;
                        }
                    }
                }
            } else {
                if ($lowestPrice > $candle['low'])
                    $lowestPrice = $candle['low'];
                if ($candle['high'] >= $buy_price * (1 + $targetProfit / 100)) {
                    $liquidationPrice = BinanceApiService::calculateLiquidationPrice($symbol, $buy_price, CommonHelpers::getSettingsValue('future_coin_report_leverage', 10), 'long');
                    $candle['should_sell'] = true;
                    $buy_triggers[] = $candle;
                    $currentTrade['sellingCandle'] = json_encode($candle);
                    $currentTrade['buyingPrice'] = $buy_price;
                    $currentTrade['market'] = $market;
                    $currentTrade['sellingPrice'] = $candle['high'];
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = $interval;
                    $currentTrade['profit'] = round(($candle['high'] - $buy_price) / $buy_price * 100, 2);
                    $currentTrade['lowestPrice'] = $lowestPrice;
                    $currentTrade['liquidationPrice'] = $liquidationPrice;
                    $currentTrade['lowestPricePercentage'] = (($buy_price - $lowestPrice) / $buy_price) * 100;

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
