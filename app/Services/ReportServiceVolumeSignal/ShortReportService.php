<?php
// Volume-Signal & Order-books (Volume Imbalance) & MFI 
namespace App\Services\ReportServiceVolumeSignal;

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
        $cmd = null
    ) {

        $tradesTotal = [];
        $coins = DB::table('coins')->where('market', $market)
            ->where('status', 'T')
            // ->whereIn('symbol', ['HBARUSDT', 'BTCUSDT', 'AVAXUSDT', 'EGLDUSDT'])
            ->limit(20)
            ->get();
        system('clear');
        $cmd->info('Processing: 0 %');
        foreach ($coins as $index => $coin) {

            $targetProfit = 0.5;

            try {



                $symbol = $coin->symbol;

                $data = BinanceApiService::getCandleStickData($symbol, '5m', 1000, null, 'FUTURE');

                $trades = self::processCandles($symbol, '5m', 'FUTURE', $data, $targetProfit, $formula);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', $interval)->where('formula', $formula)->where('market', $market)->where('position', 'SHORT')->delete();
                DB::table('coin_reports')->insert($trades);
                $tradesTotal[$symbol] = $trades;


                $perProgress = (($index + 1) / count($coins)) * 100;
                system('clear');
                $cmd->info('Processing: ' . round($perProgress) . ' %');

                // Log::info("Updated coin report for $symbol at interval $interval.");
            } catch (\Exception $e) {
                dd($e);
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(10);
        }

        $cmd->info('Completed Report for : ' . $formula);

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
        $open_price = 0;
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
        $extremePrice = 0;

        $openingIndex = 0;
        $switchTrade = false;
        $switchDirection = '';
        $switchSnapshot = new stdClass;

        $buyingCandles = [];
        $timestamp = $data[0]['binance_timestamp'] - (60 * 5000 * 1000);
        $averageAdjustmetCandles =  BinanceApiService::getCandleStickData($symbol, $interval, 1000, $timestamp, $market);
        $isAlreadySwitched = false;
        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($averageAdjustmetCandles, $data));

        $waitingCandles = 0;

        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true);

        foreach ($data as $index => $candle) {

            // Skip First 1000 Candles
            if ($index < 1001) {
                continue;
            }

            // 20 mins weight after each trade

            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }





            if ($open_price == 0) {
                $idealBuying = IdealTradeService::getIdealOpeningCandlesShort(array_slice($data, $index - 1000, 1000));
                // dd($symbol,$index,$idealBuying);
                if (empty($idealBuying))
                    continue;



                $averages = IdealTradeService::getAverages($idealBuying, 'SHORT');



                $allowOpening = false;





                $timestamp = $candle['timestamp'];
                $snapshots = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                    ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
                    ->where('symbol', $symbol)
                    ->where('depth', 1000)
                    ->latest('snapshot_time')
                    ->get();
                if (count($snapshots) < 1) {
                    continue;
                }



                $orderBookSnapshot = $snapshots[0];

                $volumeIndex = $index - 1000;
                $volumeSignal = $volumeSignals[$volumeIndex];




                // ==================LOGIC TO CHECK TRADE TYPE AND CONDITIONS==================

                // // 5 macd solid RED candles and current macd light red
                // $macdLongCondition =
                //     $data[$index]['histogram'] > $data[$index - 1]['histogram'] && $data[$index]['histogram'] < 0 // Current Candle should be light red
                //     && $data[$index - 1]['histogram'] < $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] < 0 // // Second Last Candle should be dark red
                //     && $data[$index - 2]['histogram'] < $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] < 0 // // Third Last Candle should be dark red
                //     && $data[$index - 3]['histogram'] < $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] < 0 // // Fourth Last Candle should be dark red
                //     && $data[$index - 4]['histogram'] < $data[$index - 5]['histogram'] && $data[$index - 4]['histogram'] < 0 // // Fifth Last Candle should be dark red
                //     && $data[$index - 5]['histogram'] < $data[$index - 6]['histogram'] && $data[$index - 5]['histogram'] < 0 // // Sixth Last Candle should be dark red
                //     && $data[$index - 6]['histogram'] < $data[$index - 7]['histogram'] && $data[$index - 6]['histogram'] < 0 // // Sixth Last Candle should be dark red
                // ;

                // // 5 macd loght Green candles and current macd Solid green
                // $macdShortCondition =
                //     $data[$index]['histogram'] < $data[$index - 1]['histogram'] && $data[$index]['histogram'] > 0 // Current Candle should be solid green
                //     && $data[$index - 1]['histogram'] > $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] > 0 // // Second Last Candle should be light green
                //     && $data[$index - 2]['histogram'] > $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] > 0 // // Third Last Candle should be light green
                //     && $data[$index - 3]['histogram'] > $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] > 0 // // Fourth Last Candle should be light green
                //     && $data[$index - 4]['histogram'] > $data[$index - 5]['histogram'] && $data[$index - 4]['histogram'] > 0 // // Fifth Last Candle should be light green
                //     && $data[$index - 5]['histogram'] > $data[$index - 6]['histogram'] && $data[$index - 5]['histogram'] > 0 // // Sixth Last Candle should be light green
                //     && $data[$index - 6]['histogram'] > $data[$index - 7]['histogram'] && $data[$index - 6]['histogram'] > 0 // // Sixth Last Candle should be light green
                // ;


                // 

                $imbalance = ($orderBookSnapshot->bid_volume - $orderBookSnapshot->ask_volume) / ($orderBookSnapshot->bid_volume + $orderBookSnapshot->ask_volume) * 100;
                $spread_pct = ($orderBookSnapshot->lowest_ask - $orderBookSnapshot->highest_bid) / (($orderBookSnapshot->lowest_ask + $orderBookSnapshot->highest_bid) / 2) * 100;


                // Volume Indicators
                $mfi = $volumeSignal['indicators']['mfi_current'];
                $cvd = $volumeSignal['indicators']['cvd_current'];
                $obv = $volumeSignal['indicators']['obv_current'];
                $obv_previous = $volumeSignals[$volumeIndex - 1]['indicators']['obv_current'];
                $vwap = $volumeSignal['indicators']['vwap_current'];
                // dd($volumeSignal['indicators']);
                $priceLong = $orderBookSnapshot->lowest_ask; // For LONG
                $priceShort = $orderBookSnapshot->highest_bid; // For LONG



                if (
                    $imbalance > 5 && $spread_pct < 0.01
                    && $obv > $obv_previous
                    // && $data[$index]['close'] < $vwap
                    && $mfi < 20

                    && $data[$index]['per'] > 0

                    // ADX Trend Check Upwards
                    && $data[$index]['adx'] > 20
                    && $data[$index]['adx'] < 50
                    && $data[$index]['di_plus'] > $data[$index]['di_minus']

                ) {
                    $tradeType = 'LONG';
                    $allowOpening = true;
                } elseif (
                    $imbalance < -5 && $spread_pct < 0.01
                    && $obv < $obv_previous
                    // && $data[$index]['close'] > $vwap
                    && $mfi > 80
                    && $data[$index]['per'] < 0

                    && $data[$index]['adx'] > 20
                    && $data[$index]['adx'] < 50
                    && $data[$index]['di_plus'] < $data[$index]['di_minus']
                ) {
                    $tradeType = 'SHORT';
                    $allowOpening = true;
                } else {
                    continue;
                }









                // ========================================================================



                if (
                    $allowOpening
                ) {
                    $candle['should_buy'] = true;
                    $candle['previousObvHigh'] = 0;
                    $candle['previousObvHighReduced'] = 0;
                    $open_price = $candle['close'];
                    $buy_triggers[] = $candle;
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $currentTrade['buyingAverages'] = json_encode($averages);
                    $extremePrice = $open_price;
                    $openingIndex = $index;
                }
            } else {
                $closingPrice = 0;


                if ($tradeType == 'SHORT') {

                    // Calculate the extreme price
                    if ($extremePrice < $candle['high'])
                        $extremePrice = $candle['high'];
                    // Calculate Closing in profit 
                    if ($candle['low'] <= $open_price * (1 - $targetProfit / 100)) {
                        $closingPrice = $candle['low'];
                    } else if (
                        $index - $openingIndex  >= 4
                        && CommonHelpers::getPercentDiff($open_price, $data[$index]['high']) >= 0.8
                        && $open_price < $data[$index]['high']
                        // && $data[$index]['per'] > 0
                        // && $data[$index - 1]['per'] > 0

                    ) {
                        $closingPrice = $data[$index]['high'];
                    }
                } else if ($tradeType == 'LONG') {
                    // Calculate the extreme price
                    if ($extremePrice > $candle['low'])
                        $extremePrice = $candle['low'];
                    // Calculate Closing in profit 
                    if ($candle['high'] >= $open_price * (1 + $targetProfit / 100)) {

                        $closingPrice = $candle['high'];
                    } else if (
                        $index - $openingIndex  >= 4
                        && CommonHelpers::getPercentDiff($open_price, $data[$index]['low']) >= 0.8
                        && $open_price > $data[$index]['low']
                        // && $data[$index]['per'] < 0
                        // && $data[$index - 1]['per'] < 0
                    ) {
                        $closingPrice = $data[$index]['low'];
                    }
                }





                $timestamp = $candle['timestamp'];
                $snapshots = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                    ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
                    ->where('symbol', $symbol)
                    ->where('depth', 1000)
                    ->latest('snapshot_time')
                    ->get();
                if (count($snapshots) < 1) {
                    continue;
                }
                $snapshot = $snapshots[0];

                $allowSwitching = false;







                // Closing Sequence

                if ($closingPrice) {
                    $profit = $tradeType === 'LONG' ? round(($closingPrice - $open_price) / $open_price * 100, 2) : round(($open_price - $closingPrice) / $open_price * 100, 2);
                    $liquidationPrice = BinanceApiService::calculateLiquidationPrice($symbol, $open_price, CommonHelpers::getSettingsValue('future_coin_report_leverage', 10), 'short');
                    $candle['should_sell'] = true;
                    $buy_triggers[] = $candle;
                    $currentTrade['sellingCandle'] = json_encode($candle);
                    $currentTrade['buyingPrice'] = $open_price;
                    $currentTrade['market'] = $market;
                    $currentTrade['sellingPrice'] = $closingPrice;
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = $interval;
                    $currentTrade['profit'] = $profit;
                    $currentTrade['lowestPrice'] = $extremePrice;
                    $currentTrade['liquidationPrice'] = $liquidationPrice;
                    $currentTrade['lowestPricePercentage'] = abs((($open_price - $extremePrice) / $open_price)) * 100;
                    $currentTrade['position'] = $tradeType;
                    $currentTrade['formula'] = $formula;
                    $currentTrade['snapshot_id'] = null;
                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);
                    $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                    // Resetting params
                    $extremePrice = 0;
                    $trades[] = $currentTrade;
                    $currentTrade = [];
                    $open_price = 0;
                    $snapshotOpen = null;
                    $tradeType = null;
                    $waitingCandles = 4;
                    $openingIndex = 0;



                    // Stop it from switching multiple times
                    if (!$allowSwitching && $isAlreadySwitched) {
                        $isAlreadySwitched = false;
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
