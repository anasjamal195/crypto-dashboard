<?php

namespace App\Services\InternalTrader;

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
use Illuminate\Support\Facades\File;

class ReportService
{
    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 10;
    public static $backTestTimeUnix = 1743163200000;

    public static $interval = '5m';
    public static $targetProfit = 0.5;
    public static $stopLoss = 0.8;
    public static $stopLossWaitingDuration = 20;
    public static $longEnabled = false;
    public static $shortEnabled = true;
    public static $formula = 'Internal Report - Timing Analysis Before Optimization';
    public static $earlyClosingEnabled = true;

    // Coin Selection Filters
    public static $coinLimit = 0; // Use 0 for all coins
    public static $shuffleCoins = false;

    public static $filterOnCoinType = true;
    public static $coinTypeMetaverse = true;
    public static $coinTypeAlt = true;
    public static $coinTypeMeme = false;
    public static $coinTypeDefi = true;
    public static $coinTypeNft = false;
    public static $coinTypeWeb3 = false;

    // Performance tracking variables
    private static $performanceData = [];
    private static $functionTimers = [];

    /**
     * Start timer for a function
     * 
     * @param string $functionName
     * @return void
     */
    private static function startTimer($functionName)
    {
        self::$functionTimers[$functionName] = microtime(true);
        Log::info("Starting timer for: {$functionName}");
    }

    /**
     * End timer and log elapsed time
     * 
     * @param string $functionName
     * @return float Time in seconds
     */
    private static function endTimer($functionName)
    {
        if (!isset(self::$functionTimers[$functionName])) {
            Log::warning("Timer for {$functionName} not found!");
            return 0;
        }

        $endTime = microtime(true);
        $executionTime = $endTime - self::$functionTimers[$functionName];

        // Store performance data
        if (!isset(self::$performanceData[$functionName])) {
            self::$performanceData[$functionName] = [
                'totalTime' => 0,
                'calls' => 0,
                'maxTime' => 0,
                'minTime' => PHP_FLOAT_MAX
            ];
        }

        self::$performanceData[$functionName]['totalTime'] += $executionTime;
        self::$performanceData[$functionName]['calls']++;
        self::$performanceData[$functionName]['maxTime'] = max(self::$performanceData[$functionName]['maxTime'], $executionTime);
        self::$performanceData[$functionName]['minTime'] = min(self::$performanceData[$functionName]['minTime'], $executionTime);

        Log::info("Function {$functionName} executed in: " . number_format($executionTime, 6) . " seconds");

        return $executionTime;
    }

    /**
     * Output performance summary at the end of script execution
     */
    private static function logPerformanceSummary()
    {
        Log::info("======= PERFORMANCE SUMMARY =======");

        // Sort by total time
        uasort(self::$performanceData, function ($a, $b) {
            return $b['totalTime'] <=> $a['totalTime'];
        });

        foreach (self::$performanceData as $functionName => $data) {
            $avgTime = $data['calls'] > 0 ? $data['totalTime'] / $data['calls'] : 0;

            Log::info(sprintf(
                "Function: %s | Calls: %d | Total Time: %.6fs | Avg Time: %.6fs | Min: %.6fs | Max: %.6fs",
                $functionName,
                $data['calls'],
                $data['totalTime'],
                $avgTime,
                $data['minTime'],
                $data['maxTime']
            ));
        }

        Log::info("==================================");
    }

    public static function addFormulaDetails()
    {
        self::startTimer(__FUNCTION__);

        self::$formula = self::$formula . ' - ' . Carbon::now()->format('l, F j, Y h:i A');
        $date = date('Y-m-d H:i:s');

        $classPath = app_path('Services/InternalTrader/ReportService.php');

        // Output path
        $outputPath = storage_path('app/public/formula_bkp_service_' . self::$formula . '.txt');

        $contents = File::get($classPath);
        File::put($outputPath, $contents);
        $html = '
        <div class="card card-chart">
            <div class="card-header">
                <h5 class="card-category text-warning">Formula Report</h5>
                <h4 class="card-title text-white">' . self::$formula . '</h4>
                <p class="card-category text-muted"><i class="tim-icons icon-calendar-60"></i> Generated on ' . $date . '</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table tablesorter">
                        <tbody class="text-white">
                            <tr>
                                <td><i class="tim-icons icon-time-alarm"></i> <strong>Interval:</strong></td>
                                <td>' . self::$interval . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-money-coins"></i> <strong>Target Profit:</strong></td>
                                <td>' . self::$targetProfit . '%</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-triangle-right-17"></i> <strong>Stop Loss:</strong></td>
                                <td>' . self::$stopLoss . '%</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-watch-time"></i> <strong>Stop Loss Wait Duration:</strong></td>
                                <td>' . self::$stopLossWaitingDuration . ' minutes</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-chart-bar-32"></i> <strong>Support/Resistance Candle Span:</strong></td>
                                <td>' . self::$supportResistanceCandleSpan . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-refresh-02"></i> <strong>Delay:</strong></td>
                                <td>' . self::$delayMs . ' ms</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-calendar-60"></i> <strong>Backtest Time (Unix):</strong></td>
                                <td>' . (self::$backTestTimeUnix ?? 'Not set') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-minimal-up"></i> <strong>Long Position Enabled:</strong></td>
                                <td>' . (self::$longEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-minimal-down"></i> <strong>Short Position Enabled:</strong></td>
                                <td>' . (self::$shortEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bulb-63"></i> <strong>Early Closing Enabled:</strong></td>
                                <td>' . (self::$earlyClosingEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-coins"></i> <strong>Coin Limit:</strong></td>
                                <td>' . self::$coinLimit . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-settings"></i> <strong>Filter on Coin Type:</strong></td>
                                <td>' . (self::$filterOnCoinType ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>';

        // Only show coin type details if filtering is enabled
        if (self::$filterOnCoinType) {
            $html .= '
                            <tr>
                                <td colspan="2" class="text-center"><strong class="text-info">Coin Type Filters</strong></td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-planet"></i> <strong>Metaverse:</strong></td>
                                <td>' . (self::$coinTypeMetaverse ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bitcoin"></i> <strong>Alt:</strong></td>
                                <td>' . (self::$coinTypeAlt ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-satisfied"></i> <strong>Meme:</strong></td>
                                <td>' . (self::$coinTypeMeme ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-bank"></i> <strong>DeFi:</strong></td>
                                <td>' . (self::$coinTypeDefi ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-palette"></i> <strong>NFT:</strong></td>
                                <td>' . (self::$coinTypeNft ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>
                            <tr>
                                <td><i class="tim-icons icon-world"></i> <strong>Web3:</strong></td>
                                <td>' . (self::$coinTypeWeb3 ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>') . '</td>
                            </tr>';
        }

        $html .= '
                            <tr>
                                <td><i class="tim-icons icon-cloud-download-93"></i> <strong>BKP-File path:</strong></td>
                                <td class="" style="max-width: 250px;" title="' . $outputPath . '">' . $outputPath . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="stats">
                    <i class="tim-icons icon-refresh-01"></i> Last updated ' . date('H:i:s') . '
                </div>
            </div>
        </div>
        ';

        DB::table('formula_details')->insert([
            'formula' => self::$formula,
            'details' => $html,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        self::endTimer(__FUNCTION__);
    }

    public static function generateCoinReport($cmd = null)
    {
        self::startTimer(__FUNCTION__);

        $tradesTotal = [];
        $startCoinsQuery = microtime(true);

        // Log DB query time
        Log::info("Starting database query for coins");
        $coinsQuery = DB::table('coins')->where('market', 'FUTURE')->where('status', 'T')->where('symbol', 'EGLDUSDT');

        // Coin Type Filters
        if (self::$filterOnCoinType) {
            if (self::$coinTypeMetaverse)
                $coinsQuery->where('is_metaverse', true);
            if (self::$coinTypeAlt)
                $coinsQuery->where('is_altcoin', true);
            if (self::$coinTypeMeme)
                $coinsQuery->where('is_meme_coin', true);
            if (self::$coinTypeNft)
                $coinsQuery->where('is_nft', true);
            if (self::$coinTypeDefi)
                $coinsQuery->where('is_defi', true);
            if (self::$coinTypeWeb3)
                $coinsQuery->where('is_web3', true);
        }
        if (self::$shuffleCoins) {
            $coinsQuery->inRandomOrder();
        }

        if (self::$coinLimit) {
            $coinsQuery->limit(self::$coinLimit);
        } else {
            $countQuery = microtime(true);
            self::$coinLimit = (clone $coinsQuery)->count();
            Log::info("Counting coins took: " . number_format(microtime(true) - $countQuery, 6) . " seconds");
        }

        $getCoinsTime = microtime(true);
        $coins = $coinsQuery->get();
        Log::info("Fetching coins took: " . number_format(microtime(true) - $getCoinsTime, 6) . " seconds");
        Log::info("Total DB query for coins took: " . number_format(microtime(true) - $startCoinsQuery, 6) . " seconds");

        // Clear Console
        system('clear');
        $cmd->info('Processing: 0 %');

        self::addFormulaDetails();

        $totalProcessingTime = 0;
        $totalDataRetrievalTime = 0;
        $totalProcessCandlesTime = 0;
        $totalDBInsertTime = 0;

        foreach ($coins as $index => $coin) {
            $coinStartTime = microtime(true);
            try {
                $symbol = $coin->symbol;
                Log::info("Processing coin: " . $symbol . " (" . ($index + 1) . "/" . $coins->count() . ")");

                // Time for data retrieval
                self::startTimer("getCandleStickData_{$symbol}");
                $data = BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, self::$backTestTimeUnix, 'FUTURE');
                $dataRetrievalTime = self::endTimer("getCandleStickData_{$symbol}");
                $totalDataRetrievalTime += $dataRetrievalTime;

                // Time for processing candles
                self::startTimer("processCandles_{$symbol}");
                $trades = self::processCandles($symbol, $data);
                $processCandlesTime = self::endTimer("processCandles_{$symbol}");
                $totalProcessCandlesTime += $processCandlesTime;

                // Time for DB operations
                self::startTimer("DBOperations_{$symbol}");
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();
                DB::table('coin_reports')->insert($trades);
                $dbOperationTime = self::endTimer("DBOperations_{$symbol}");
                $totalDBInsertTime += $dbOperationTime;

                $tradesTotal[$symbol] = $trades;

                $perProgress = (($index + 1) / count($coins)) * 100;
                system('clear');
                $cmd->info('Processing: ' . round($perProgress) . ' %');
                DB::table('formula_details')->where('formula', self::$formula)->update([
                    'progress' => $perProgress,
                ]);

                $totalCoinTime = microtime(true) - $coinStartTime;
                Log::info("Total processing time for {$symbol}: " . number_format($totalCoinTime, 6) . " seconds");
                Log::info("  - Data retrieval: " . number_format($dataRetrievalTime, 6) . " seconds (" . number_format(($dataRetrievalTime / $totalCoinTime) * 100, 2) . "%)");
                Log::info("  - Process candles: " . number_format($processCandlesTime, 6) . " seconds (" . number_format(($processCandlesTime / $totalCoinTime) * 100, 2) . "%)");
                Log::info("  - DB operations: " . number_format($dbOperationTime, 6) . " seconds (" . number_format(($dbOperationTime / $totalCoinTime) * 100, 2) . "%)");

                $totalProcessingTime += $totalCoinTime;
            } catch (\Exception $e) {
                $cmd->error('Error Occurred: ' . $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        Log::info("==== OVERALL PERFORMANCE STATISTICS ====");
        Log::info("Total processing time: " . number_format($totalProcessingTime, 6) . " seconds");
        Log::info("Average time per coin: " . number_format($totalProcessingTime / count($coins), 6) . " seconds");
        Log::info("Total data retrieval time: " . number_format($totalDataRetrievalTime, 6) . " seconds (" . number_format(($totalDataRetrievalTime / $totalProcessingTime) * 100, 2) . "%)");
        Log::info("Total process candles time: " . number_format($totalProcessCandlesTime, 6) . " seconds (" . number_format(($totalProcessCandlesTime / $totalProcessingTime) * 100, 2) . "%)");
        Log::info("Total DB insert time: " . number_format($totalDBInsertTime, 6) . " seconds (" . number_format(($totalDBInsertTime / $totalProcessingTime) * 100, 2) . "%)");

        self::logPerformanceSummary();

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));

        self::endTimer(__FUNCTION__);
    }

    protected static function processCandles($symbol, $data)
    {
        self::startTimer(__FUNCTION__ . "_{$symbol}");

        $open_price = 0;
        $tradeType = null;
        $currentTrade = [];
        $trades = [];
        $extremePrice = 0;

        $intervalToMins = CommonHelpers::$binanceIntervals[self::$interval];

        self::startTimer("getAdjustmentCandles_{$symbol}");
        $timestamp = $data[0]['binance_timestamp'] - (60 * $intervalToMins * 1000 * 1000);
        $averageAdjustmetCandles = BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, $timestamp, 'FUTURE');
        $timeForAdjustmentCandles = self::endTimer("getAdjustmentCandles_{$symbol}");
        Log::info("Getting adjustment candles for {$symbol} took: " . number_format($timeForAdjustmentCandles, 6) . " seconds");

        self::startTimer("processingCandleData_{$symbol}");
        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] = $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($averageAdjustmetCandles, $data));
        $timeForProcessingData = self::endTimer("processingCandleData_{$symbol}");
        Log::info("Processing candle data for {$symbol} took: " . number_format($timeForProcessingData, 6) . " seconds");

        $waitingCandles = 0;
        $openingIndex = 0;

        self::startTimer("getVolumeSignals_{$symbol}");
        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, self::$interval, true, $data[0]['binance_timestamp'], 1000);
        $timeForVolumeSignals = self::endTimer("getVolumeSignals_{$symbol}");
        Log::info("Getting volume signals for {$symbol} took: " . number_format($timeForVolumeSignals, 6) . " seconds");

        $supportResistanceTime = 0;
        $orderBookTime = 0;
        $openingConditionsTime = 0;
        $closingConditionsTime = 0;

        self::startTimer("processingCandleLoop_{$symbol}");
        $candlesProcessed = 0;

        foreach ($data as $index => $candle) {
            $candlesProcessed++;
            $volumeIndex = $index - 1000;

            // Skip Adjustment Candles and Volume Adjustment
            if ($index < 1000) {
                continue;
            }

            // 20 mins weight after each trade
            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }

            // Time support/resistance calculation
            $startSR = microtime(true);
            $supportResistance = self::getSupportResistance($data, $index);
            $supportResistanceTime += microtime(true) - $startSR;

            // Time order book snapshot retrieval
            $startOB = microtime(true);
            $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);
            $orderBookTime += microtime(true) - $startOB;

            if ($open_price == 0) {
                // Time opening conditions check
                $startOC = microtime(true);
                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $supportResistance, $orderBookSnapshot);
                $openingConditionsTime += microtime(true) - $startOC;

                if ($tradeType) {
                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot->id;
                    $candle['openingVolumes'] = json_encode($volumeSignals[$volumeIndex]);

                    $open_price = $candle['close'];
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $extremePrice = $open_price;
                    $openingIndex = $index;
                }
            } else {
                // Time closing conditions check
                $startCC = microtime(true);
                $closingPrice = self::handleClosingConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $tradeType, $openingIndex, $open_price);
                $closingConditionsTime += microtime(true) - $startCC;

                // Closing Sequence
                if ($tradeType === 'SHORT' && $data[$index]['high'] > $extremePrice) {
                    $extremePrice = $data[$index]['high'];
                }
                if ($tradeType === 'LONG' && $data[$index]['low'] < $extremePrice) {
                    $extremePrice = $data[$index]['low'];
                }
                if ($closingPrice) {
                    $profit = $tradeType === 'LONG' ? round(($closingPrice - $open_price) / $open_price * 100, 2) : round(($open_price - $closingPrice) / $open_price * 100, 2);

                    $candle['should_sell'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                    $candle['closingVolumes'] = json_encode($volumeSignals[$volumeIndex]);

                    $currentTrade['sellingCandle'] = json_encode($candle);
                    $currentTrade['buyingPrice'] = $open_price;
                    $currentTrade['market'] = 'FUTURE';
                    $currentTrade['sellingPrice'] = $closingPrice;
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = self::$interval;
                    $currentTrade['profit'] = $profit;
                    $currentTrade['lowestPrice'] = $extremePrice;
                    $currentTrade['liquidationPrice'] = 0;
                    $currentTrade['lowestPricePercentage'] = abs((($open_price - $extremePrice) / $open_price)) * 100;
                    $currentTrade['position'] = $tradeType;
                    $currentTrade['formula'] = self::$formula;

                    $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['buyingCandle'], true)['timestamp']);
                    $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($currentTrade['sellingCandle'], true)['timestamp']);
                    $currentTrade['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                    // Resetting params
                    $extremePrice = 0;
                    $trades[] = $currentTrade;
                    $currentTrade = [];
                    $open_price = 0;
                    $tradeType = null;
                    $waitingCandles = 4;
                    $openingIndex = 0;
                }
            }
        }

        $candleLoopTime = self::endTimer("processingCandleLoop_{$symbol}");
        Log::info("Candle loop processing for {$symbol} took: " . number_format($candleLoopTime, 6) . " seconds");
        Log::info("  - Candles processed: {$candlesProcessed}");
        Log::info("  - Support/Resistance calculations: " . number_format($supportResistanceTime, 6) . " seconds (" . number_format(($supportResistanceTime / $candleLoopTime) * 100, 2) . "%)");
        Log::info("  - Order Book retrieval: " . number_format($orderBookTime, 6) . " seconds (" . number_format(($orderBookTime / $candleLoopTime) * 100, 2) . "%)");
        Log::info("  - Opening conditions: " . number_format($openingConditionsTime, 6) . " seconds (" . number_format(($openingConditionsTime / $candleLoopTime) * 100, 2) . "%)");
        Log::info("  - Closing conditions: " . number_format($closingConditionsTime, 6) . " seconds (" . number_format(($closingConditionsTime / $candleLoopTime) * 100, 2) . "%)");

        // For shifting indexes
        $data_new = [];
        foreach ($data as $d) {
            $data_new[] = $d;
        }
        $data = $data_new;

        self::endTimer(__FUNCTION__ . "_{$symbol}");
        return $trades;
    }

    // Function to check opening Conditions
    public static function handleOpeningConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $supportResistance, $orderBookSnapshot)
    {
        self::startTimer(__FUNCTION__ . "_{$symbol}_{$index}");

        if ($volumeIndex < 10) {
            self::endTimer(__FUNCTION__ . "_{$symbol}_{$index}");
            return null;
        }

        if (!$orderBookSnapshot) {
            self::endTimer(__FUNCTION__ . "_{$symbol}_{$index}");
            return null;
        }

        $buyLongCondition = false;

        // SHORT condition logic
        $sellShortCondition = false;
        $shortLoopStartTime = microtime(true);
        $shortLoopIterations = 0;

        if ($data[$index]['per'] < -0.08) {
            $loopIndex = $index;
            while ($data[$loopIndex]['per'] > 0 || $loopIndex == $index) {
                $shortLoopIterations++;
                $volumeIndexLoop = $volumeIndex - ($index - $loopIndex);

                $orderBookSnapshotStartTime = microtime(true);
                $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $loopIndex);
                $orderBookSnapshotTime = microtime(true) - $orderBookSnapshotStartTime;

                if (!$orderBookSnapshotLoop) {
                    break;
                }

                $imbalanceCalcStartTime = microtime(true);
                $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
                $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;
                $imbalanceCalcTime = microtime(true) - $imbalanceCalcStartTime;




                $volumeIndicatorsStartTime = microtime(true);
                $mfi = $volumeSignals[$volumeIndexLoop]['indicators']['mfi_current'];
                $cvd = $volumeSignals[$volumeIndexLoop]['indicators']['cvd_current'];
                $obv = $volumeSignals[$volumeIndexLoop]['indicators']['obv_current'];
                $obv_previous = $volumeSignals[$volumeIndexLoop - 1]['indicators']['obv_current'];
                $vwap = $volumeSignals[$volumeIndexLoop]['indicators']['vwap_current'];
                $volumeIndicatorsTime = microtime(true) - $volumeIndicatorsStartTime;

                $macdConditionStartTime = microtime(true);
                $macdShortConditionLoop =
                    $data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] > 0 // Current Candle should be solid green
                    && $data[$loopIndex - 1]['histogram'] > $data[$loopIndex - 2]['histogram'] && $data[$loopIndex - 1]['histogram'] > 0 // // Second Last Candle should be light green
                    && $data[$loopIndex - 2]['histogram'] > $data[$loopIndex - 3]['histogram'] && $data[$loopIndex - 2]['histogram'] > 0 // // Third Last Candle should be light green
                    && $data[$loopIndex - 3]['histogram'] > $data[$loopIndex - 4]['histogram'] && $data[$loopIndex - 3]['histogram'] > 0 // // Fourth Last Candle should be light green
                    && $data[$loopIndex - 4]['histogram'] > $data[$loopIndex - 5]['histogram'] && $data[$loopIndex - 4]['histogram'] > 0 // // Fifth Last Candle should be light green
                    && $data[$loopIndex - 5]['histogram'] > $data[$loopIndex - 6]['histogram'] && $data[$loopIndex - 5]['histogram'] > 0 // // Sixth Last Candle should be light green
                ;
                $macdConditionTime = microtime(true) - $macdConditionStartTime;

                $sellShortCondition =
                    $imbalance < -5
                    && $spread_pct < 0.1
                    && $macdShortConditionLoop
                    && $volumeSignals[$volumeIndexLoop]['indicators']['mfi_current'] > 70;

                if ($shortLoopIterations % 10 == 0) {
                    Log::debug(
                        "Short condition loop for {$symbol} iteration {$shortLoopIterations}: " .
                            "OrderBook snapshot: " . number_format($orderBookSnapshotTime, 6) . "s, " .
                            "Imbalance calc: " . number_format($imbalanceCalcTime, 6) . "s, " .
                            "Volume indicators: " . number_format($volumeIndicatorsTime, 6) . "s, " .
                            "MACD condition: " . number_format($macdConditionTime, 6) . "s"
                    );
                }

                $loopIndex--;

                if ($sellShortCondition || $volumeIndex <= 1)
                    break;
            }
        }

        if ($shortLoopIterations > 0) {
            Log::info("Short condition loop for {$symbol} ran {$shortLoopIterations} iterations in " .
                number_format(microtime(true) - $shortLoopStartTime, 6) . " seconds");
        }

        // Return trade type based on conditions
        $result = null;
        if ($sellShortCondition) {
            $result = self::$shortEnabled ? 'SHORT' : null;
        } else if ($buyLongCondition) {
            $result = self::$longEnabled ? 'LONG' : null;
        }

        self::endTimer(__FUNCTION__ . "_{$symbol}_{$index}");
        return $result;
    }

    public static function handleClosingConditions($symbol, $data, $index, $volumeSignals, $volumeIndex, $tradeType, $openingIndex, $open_price)
    {
        self::startTimer(__FUNCTION__ . "_{$symbol}_{$index}");

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);

        $profitCalcStartTime = microtime(true);
        if ($tradeType == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
                Log::debug("{$symbol} SHORT position closing with profit at price: {$closingPrice}");
            } else if (
                $index - $openingIndex >= $waitingCandlesBeforeStopLoss &&
                CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss &&
                $open_price < $data[$index]['close']
            ) {
                $closingPrice = $data[$index]['close'];
                Log::debug("{$symbol} SHORT position closing with stop loss at price: {$closingPrice}");
            }
        } else if ($tradeType == 'LONG') {
            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
                Log::debug("{$symbol} LONG position closing with profit at price: {$closingPrice}");
            } else if (
                $index - $openingIndex >= $waitingCandlesBeforeStopLoss &&
                CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss &&
                $open_price > $data[$index]['close']
            ) {
                $closingPrice = $data[$index]['close'];
                Log::debug("{$symbol} LONG position closing with stop loss at price: {$closingPrice}");
            }
        }

        $profitCalcTime = microtime(true) - $profitCalcStartTime;
        if ($profitCalcTime > 0.001) {  // Only log if it took more than 1ms
            Log::debug("Profit calculation for {$symbol} ({$tradeType}) took " . number_format($profitCalcTime, 6) . " seconds");
        }

        self::endTimer(__FUNCTION__ . "_{$symbol}_{$index}");
        return $closingPrice;
    }

    public static function getSupportResistance($data, $index)
    {
        self::startTimer(__FUNCTION__ . "_{$index}");

        $end = $index + 1; // +1 to include the $index item
        $length = 300;

        $start = max(0, $end - $length); // make sure we don't go negative
        $slicedData = array_slice($data, $start, $length);

        $srStartTime = microtime(true);
        $result = MarketTrendService::getCurrentSupportResistanceValueFromData(
            $slicedData,
            [self::$supportResistanceCandleSpan]
        )[self::$supportResistanceCandleSpan];
        $srTime = microtime(true) - $srStartTime;

        if ($srTime > 0.005) {  // Only log if it took more than 5ms
            Log::debug("Support/Resistance calculation for candle index {$index} took " .
                number_format($srTime, 6) . " seconds");
        }

        self::endTimer(__FUNCTION__ . "_{$index}");
        return $result;
    }

    public static function getOrderBookSnapshot($symbol, $data, $index)
    {
        self::startTimer(__FUNCTION__ . "_{$symbol}_{$index}");

        // Fetch OrderBook snapshot
        $timestamp = $data[$index]['timestampReadable'];

        $queryStartTime = microtime(true);
        $snapshot = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
            ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
            ->where('symbol', $symbol)
            ->where('depth', 1000)
            ->latest('snapshot_time')
            ->first();
        $queryTime = microtime(true) - $queryStartTime;

        if ($queryTime > 0.01) {  // Only log if it took more than 10ms
            Log::debug("OrderBookSnapshot query for {$symbol} at index {$index} took " .
                number_format($queryTime, 6) . " seconds");
        }

        self::endTimer(__FUNCTION__ . "_{$symbol}_{$index}");
        return $snapshot;
    }
}
