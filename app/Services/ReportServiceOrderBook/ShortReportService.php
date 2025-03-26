<?php

namespace App\Services\ReportServiceOrderBook;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\OrderBookSnapshot;

use Illuminate\Support\Facades\Log;
use stdClass;

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
        $snapshotOpen = new stdClass;
        $tradeType = null;
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

        $triggerPrice = 0;
        $triggerIndex = 0;
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

            if ($buy_price == 0) {

                $allowOpening = false;
                if (!$triggerPrice) {
                    $timestamp = $candle['timestamp'];
                    $snapshot = OrderBookSnapshot::where('snapshot_time', '>=', $timestamp)
                        ->where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                        ->where('symbol', $symbol)
                        ->where('signal', 'SHORT')
                        ->where('depth', 1000)
                        ->where('short_strength', '>=', 8)
                        ->latest('snapshot_time')
                        ->first();

                    if (!$snapshot) {
                        continue;
                    }

                    $entry_points = array_map(function ($level) {
                        return $level['price'];
                    }, $snapshot->resistance_levels);

                    $entry_points_reverse = array_map(function ($level) {
                        return $level['price'];
                    }, $snapshot->support_levels);


                    $triggerPriceShort = min($entry_points);
                    $triggerPriceLong = max($entry_points_reverse);
                    $snapshotOpen = $snapshot;

                    if (round(CommonHelpers::getPercentDiff($data[$index]['close'], $triggerPriceShort), 2) >= round(CommonHelpers::getPercentDiff($data[$index]['close'], $triggerPriceLong), 2) ) {
                        $tradeType = 'LONG';
                        $triggerPrice = $triggerPriceShort;
                        $triggerIndex = $index;
                    } else {
                        $triggerPrice = $triggerPriceLong;
                        $triggerIndex = $index;
                        $tradeType = 'SHORT';
                    }
                } else {
                    if ($tradeType == 'SHORT') {
                        if ($candle['high'] >= $triggerPrice) {
                            $allowOpening = true;
                            $triggerPrice = 0;
                            $triggerIndex = 0;
                        } else if ($index - $triggerIndex > 20) {
                            $allowOpening = false;
                            $triggerPrice = 0;
                            $triggerIndex = 0;
                            $snapshotOpen = null;
                        }
                    } else if ($tradeType == 'LONG') {
                        if ($candle['low'] <= $triggerPrice) {
                            $allowOpening = true;
                            $triggerPrice = 0;
                            $triggerIndex = 0;
                        } else if ($index - $triggerIndex > 20) {
                            $allowOpening = false;
                            $triggerPrice = 0;
                            $triggerIndex = 0;
                        }
                    }
                }



                if ($tradeType == 'SHORT') {
                    $volumeCondition = true;
                    $volumeAverage = 0;
                    for ($i = $index; $i >= $index - 20; $i--) {
                        $volumeAverage += $data[$i]['volume'];
                    }

                    $volumeAverage = $volumeAverage / 10;

                    for ($i = $index; $i >= $index - 10; $i--) {
                        if ($data[$i]['volume'] > 2 * $volumeAverage && $data[$i]['per'] > 0) {
                            $volumeCondition = false;
                            break;
                        }
                    }
                    if (
                        $allowOpening
                        && $volumeCondition
                        &&
                        !(
                            $data[$index]['dif'] > $data[$index]['dea']
                            && $data[$index - 1]['dif'] < $data[$index - 1]['dea']
                        )

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
                } else if ($tradeType == 'LONG') {
                    $volumeCondition = true;
                    $volumeAverage = 0;
                    for ($i = $index; $i >= $index - 10; $i--) {
                        $volumeAverage += $data[$i]['volume'];
                    }

                    $volumeAverage = $volumeAverage / 10;

                    for ($i = $index; $i >= $index - 10; $i--) {
                        if ($data[$i]['volume'] > 2 * $volumeAverage && $data[$i]['per'] < 0) {
                            $volumeCondition = false;
                            break;
                        }
                    }

                    if (
                        $allowOpening
                        && $volumeCondition
                        &&
                        !(
                            $data[$index]['dif'] < $data[$index]['dea']
                            && $data[$index - 1]['dif'] > $data[$index - 1]['dea']
                        )
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

                if ($tradeType == 'SHORT') {
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
                        $currentTrade['snapshot_id'] = $snapshotOpen->id;
                        $lowestPrice = 0;
                        $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                        $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);
                        $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;
                        $trades[] = $currentTrade;
                        $currentTrade = [];
                        $buy_price = 0;
                        $snapshotOpen = null;
                        $tradeType = null;

                    }
                } else if ($tradeType == 'LONG') {
                    if ($lowestPrice > $candle['low'])
                        $lowestPrice = $candle['low'];

                    if ($candle['close'] >= $buy_price * (1 + $targetProfit / 100)) {
                        $liquidationPrice = BinanceApiService::calculateLiquidationPrice($symbol, $buy_price, CommonHelpers::getSettingsValue('future_coin_report_leverage', 10), 'long');
                        $candle['should_sell'] = true;
                        $buy_triggers[] = $candle;
                        $currentTrade['sellingCandle'] = json_encode($candle);
                        $currentTrade['buyingPrice'] = $buy_price;
                        $currentTrade['market'] = $market;
                        $currentTrade['sellingPrice'] = $candle['close'];
                        $currentTrade['symbol'] = $symbol;
                        $currentTrade['interval'] = $interval;
                        $currentTrade['profit'] = round(($candle['close'] - $buy_price) / $buy_price * 100, 2);
                        $currentTrade['lowestPrice'] = $lowestPrice;
                        $currentTrade['liquidationPrice'] = $liquidationPrice;
                        $currentTrade['lowestPricePercentage'] = (($buy_price - $lowestPrice) / $buy_price) * 100;
                        $currentTrade['position'] = 'LONG';
                        $currentTrade['formula'] = $formula;
                        $currentTrade['snapshot_id'] = $snapshotOpen->id;

                        $lowestPrice = 0;
                        $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                        $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);

                        $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                        $trades[] = $currentTrade;
                        $currentTrade = [];
                        $buy_price = 0;
                        $snapshotOpen = null;
                        $tradeType = null;
                    }
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
