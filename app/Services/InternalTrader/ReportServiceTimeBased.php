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

class ReportServiceTimeBased
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 4;
    public static $backTestTimeUnix = 1745193600000;
    public static $suppressErrors = false;

    // Enable it if you want fresh data dump of candles in db, Disabled 
    public static $freshCoinDataDump = false;


    public static $interval = '5m';
    public static $targetProfit = 0.5;
    public static $stopLoss = 1;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = true;
    public static $formula = 'Time Based Report';
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





    // Confirmed Trades table settings

    public static $candlesToCheck = 500;

    public static $volumeMA5ValidFor = 30;
    public static $difValidFor = 30;
    public static $obvValidFor = 30;
    public static $kdjValidFor = 30;
    public static $bollValidFor = 30;
    public static $bollSqueezValidFor = 30;



    public static function addFormulaDetails()
    {
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
    }



    public static function generateCoinReport(
        $cmd = null
    ) {


        $coinsQuery = DB::table('coins')->where('market', 'FUTURE')->where('status', 'T');


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
            self::$coinLimit = (clone $coinsQuery)->count();
        }
        $coins = $coinsQuery->get();


        if (self::$freshCoinDataDump) {
            system('clear');
            $cmd->info('Dumping Coin Data...');
            CommonHelpers::dumpCoinData($coins, self::$interval, self::$backTestTimeUnix);
        }
        // Clear Console
        system('clear');


        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();


        self::processCandles($coins, $cmd);


        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
    }

    protected static function processCandles($coins, $cmd)
    {


        $timestamps = CommonHelpers::getUniqueTimestampsFromDb();


        foreach ($timestamps as $index => $timestamp) {


            try {

                if ($index < 250) {
                    continue;
                }




                foreach ($coins as $coinIndex => $coin) {
                    $startTime = microtime(true);
                    $operationTimes = [
                        'data_retrieval' => 0,
                        'support_resistance' => 0,
                        'order_book' => 0,
                        'open_trade_check' => 0,
                        'long_conditions' => 0,
                        'short_conditions' => 0,
                        'closing_conditions' => 0,
                        'total' => 0
                    ];

                    try {
                        $symbol = $coin->symbol;

                        // Measure data retrieval time
                        $timeStart = microtime(true);
                        $data = CommonHelpers::getCoinDataFromDb($symbol, self::$interval, 'FUTURE');
                        $candle = $data[$index];
                        $operationTimes['data_retrieval'] = microtime(true) - $timeStart;

                        $volumeSignal  = [
                            "symbol" => $symbol,
                            "interval" => self::$interval,
                            "price" => $data[$index]['close'],
                            "signal" => 'NEUTRAL',
                            "strength" => 5.0,
                            "reasons" => [],
                            "potential" => false,
                            "indicators" => [
                                "mfi_current" => $data[$index]['mfi'],
                                "cvd_current" => $data[$index]['cvd'],
                                "price_to_poc" => null,
                                "poc_value" => null,
                                "volume_profile" => null,
                                "vwap_current" => $data[$index]['vwap'],
                                "obv_current" => $data[$index]['obv'],
                            ],
                            "timestampReadable" => $data[$index]['timestampReadable'],
                            "timestamp" => $data[$index]['binance_timestamp'],
                        ];

                        // Measure support/resistance calculation time
                        $timeStart = microtime(true);
                        $supportResistance = self::getSupportResistance($data, $index);
                        $operationTimes['support_resistance'] = microtime(true) - $timeStart;

                        // Measure order book snapshot retrieval time
                        $timeStart = microtime(true);
                        $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);
                        $operationTimes['order_book'] = microtime(true) - $timeStart;

                        // Measure open trade check time
                        $timeStart = microtime(true);
                        $openTradeLong = CommonHelpers::checkOpenTradeInternal($symbol, self::$interval, 'LONG', self::$formula);
                        $operationTimes['open_trade_check'] = microtime(true) - $timeStart;

                        // ###########################  Handling LONG Sequence Independently ###########################
                        if (!$openTradeLong && self::$longEnabled) {
                            $timeStart = microtime(true);
                            $tradeTypeLong = self::handleOpeningConditionsLong($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $confirmIndexLong);
                            $operationTimes['long_conditions'] = microtime(true) - $timeStart;

                            if ($tradeTypeLong) {
                                $candle['should_buy'] = true;
                                $candle['currentSupport'] = $supportResistance['support'];
                                $candle['currentResistance'] = $supportResistance['resistance'];
                                $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                                $candle['openingVolumes'] = json_encode($volumeSignal);

                                $extremePriceLong = $candle['low'];

                                $highestCandeIndex = self::getTightestSqueezIndex($data, $index);
                                $confirmCandleIndex = self::getConfirmIndex($symbol, 'LONG', $data, $index);

                                // Prepare opening trade data
                                $openingTradeData = [
                                    'symbol' => $symbol,
                                    'interval' => self::$interval,
                                    'market' => 'FUTURE',
                                    'position' => 'LONG',
                                    'buyingCandle' =>  json_encode($candle),
                                    'highestCandle' =>  json_encode($data[$highestCandeIndex]),
                                    'confirmCandle' =>  json_encode($data[$confirmCandleIndex]),
                                    'buyingPrice' => $candle['close'],
                                    'liquidationPrice' => 0,
                                    'lowestPrice' => $extremePriceLong,
                                    'formula' => self::$formula,
                                    'created_at' => Carbon::now()->toDateTimeString(),
                                ];

                                // Opening Internal Trade
                                CommonHelpers::openInternalTrade($openingTradeData);
                            }
                        } else {
                            $timeStart = microtime(true);
                            $open_price_long = $openTradeLong->buyingPrice;
                            $buyingCandle = json_decode($openTradeLong->buyingCandle, true);
                            $confirmCandle = json_decode($openTradeLong->confirmCandle, true);

                            $openingIndexLong = $index - self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $buyingCandle['binance_timestamp'], self::$interval, true);
                            $confirmIndexLong = $index - self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $confirmCandle['binance_timestamp'], self::$interval, true);
                            $tradeTypeLong = $openTradeLong->position;

                            $closingPrice = self::handleClosingConditionsLong($symbol, $data, $index, $tradeTypeLong, $openingIndexLong, $open_price_long);
                            $operationTimes['closing_conditions'] = microtime(true) - $timeStart;

                            $extremePriceLong = $openTradeLong->lowestPrice;
                            // Calculate Extreme Price 
                            for ($i = $openingIndexLong; $i <= $index; $i++) {
                                if ($data[$i]['low'] < $extremePriceLong) {
                                    $extremePriceLong = $data[$i]['low'];
                                }
                            }

                            if ($closingPrice) {
                                $profit = $tradeTypeLong === 'LONG' ? round(($closingPrice - $open_price_long) / $open_price_long * 100, 2) : round(($open_price_long - $closingPrice) / $open_price_long * 100, 2);

                                $highestPointIndex = self::getTightestSqueezIndex($data, $confirmIndexLong ?? $openingIndexLong);

                                $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);
                                $bbDiffConfirmed = CommonHelpers::getPercentDiff($data[$confirmIndexLong]['bb_lower'], $data[$confirmIndexLong]['bb_upper']);
                                $bbDiff = ($bbDiffConfirmed - $bbDiffHighest) / max(0.00001, $bbDiffHighest) * 100;

                                $data[$confirmIndexLong]['bb_diff_highest'] = $bbDiffHighest;
                                $data[$confirmIndexLong]['bb_diff_confirmed'] = $bbDiffConfirmed;
                                $data[$confirmIndexLong]['bb_diff'] = $bbDiff;
                                $candle['should_sell'] = true;
                                $candle['currentSupport'] = $supportResistance['support'];
                                $candle['currentResistance'] = $supportResistance['resistance'];
                                $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                                $candle['closingVolumes'] = json_encode($volumeSignal);

                                $closingTradeData = [];
                                $closingTradeData['sellingCandle'] = json_encode($candle);
                                $closingTradeData['sellingPrice'] = $closingPrice;
                                $closingTradeData['profit'] = $profit;
                                $closingTradeData['lowestPrice'] = $extremePriceLong;
                                $closingTradeData['lowestPricePercentage'] = abs((($open_price_long - $extremePriceLong) / $open_price_long)) * 100;

                                $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', $buyingCandle['timestampReadable']);
                                $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($closingTradeData['sellingCandle'], true)['timestampReadable']);
                                $closingTradeData['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                                CommonHelpers::closeInternalTrade($openTradeLong->id, $closingTradeData);
                            }
                        }

                        // ###########################  Handling SHORT Sequence Independently ###########################
                        $timeStart = microtime(true);
                        $openTradeShort = CommonHelpers::checkOpenTradeInternal($symbol, self::$interval, 'SHORT', self::$formula);

                        if (!$openTradeShort && self::$shortEnabled) {
                            $tradeTypeShort = self::handleOpeningConditionsShort($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $confirmIndexShort);
                            $operationTimes['short_conditions'] = microtime(true) - $timeStart;

                            if ($tradeTypeShort) {
                                $candle['should_buy'] = true;
                                $candle['currentSupport'] = $supportResistance['support'];
                                $candle['currentResistance'] = $supportResistance['resistance'];
                                $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                                $candle['openingVolumes'] = json_encode($volumeSignal);

                                $extremePriceShort = $candle['high'];

                                $highestCandeIndex = self::getTightestSqueezIndex($data, $index);
                                $confirmCandleIndex = self::getConfirmIndex($symbol, 'SHORT', $data, $index);

                                // Prepare opening trade data
                                $openingTradeData = [
                                    'symbol' => $symbol,
                                    'interval' => self::$interval,
                                    'market' => 'FUTURE',
                                    'position' => 'SHORT',
                                    'buyingCandle' =>  json_encode($candle),
                                    'highestCandle' =>  json_encode($data[$highestCandeIndex]),
                                    'confirmCandle' =>  json_encode($data[$confirmCandleIndex]),
                                    'buyingPrice' => $candle['close'],
                                    'liquidationPrice' => 0,
                                    'lowestPrice' => $extremePriceShort,
                                    'formula' => self::$formula,
                                    'created_at' => Carbon::now()->toDateTimeString(),
                                ];

                                // Opening Internal Trade
                                CommonHelpers::openInternalTrade($openingTradeData);
                            }
                        } else {
                            $open_price_short = $openTradeShort->buyingPrice;
                            $buyingCandle = json_decode($openTradeShort->buyingCandle, true);
                            $confirmCandle = json_decode($openTradeShort->confirmCandle, true);

                            $openingIndexShort = $index - self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $buyingCandle['binance_timestamp'], self::$interval, true);
                            $confirmIndexShort = $index - self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $confirmCandle['binance_timestamp'], self::$interval, true);
                            $tradeTypeShort = $openTradeShort->position;

                            $closingPrice = self::handleClosingConditionsShort($symbol, $data, $index, $tradeTypeShort, $openingIndexShort, $open_price_short);

                            $extremePriceShort = $openTradeShort->lowestPrice;
                            // Calculate Extreme Price 
                            for ($i = $openingIndexShort; $i <= $index; $i++) {
                                if ($data[$i]['low'] < $extremePriceShort) {
                                    $extremePriceShort = $data[$i]['low'];
                                }
                            }

                            if ($closingPrice) {
                                $profit = $tradeTypeShort === 'SHORT' ? round(($closingPrice - $open_price_short) / $open_price_short * 100, 2) : round(($open_price_short - $closingPrice) / $open_price_short * 100, 2);

                                $highestPointIndex = self::getTightestSqueezIndex($data, $confirmIndexShort ?? $openingIndexShort);

                                $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);
                                $bbDiffConfirmed = CommonHelpers::getPercentDiff($data[$confirmIndexShort]['bb_lower'], $data[$confirmIndexShort]['bb_upper']);
                                $bbDiff = ($bbDiffConfirmed - $bbDiffHighest) / max(0.00001, $bbDiffHighest) * 100;

                                $data[$confirmIndexShort]['bb_diff_highest'] = $bbDiffHighest;
                                $data[$confirmIndexShort]['bb_diff_confirmed'] = $bbDiffConfirmed;
                                $data[$confirmIndexShort]['bb_diff'] = $bbDiff;
                                $candle['should_sell'] = true;
                                $candle['currentSupport'] = $supportResistance['support'];
                                $candle['currentResistance'] = $supportResistance['resistance'];
                                $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                                $candle['closingVolumes'] = json_encode($volumeSignal);

                                $closingTradeData = [];
                                $closingTradeData['sellingCandle'] = json_encode($candle);
                                $closingTradeData['sellingPrice'] = $closingPrice;
                                $closingTradeData['profit'] = $profit;
                                $closingTradeData['lowestPrice'] = $extremePriceShort;
                                $closingTradeData['lowestPricePercentage'] = abs((($open_price_short - $extremePriceShort) / $open_price_short)) * 100;

                                $buyingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', $buyingCandle['timestampReadable']);
                                $sellingTimestamp = DateTime::createFromFormat('Y-m-d H:i:s', json_decode($closingTradeData['sellingCandle'], true)['timestampReadable']);
                                $closingTradeData['duration'] = ($sellingTimestamp->getTimestamp() - $buyingTimestamp->getTimestamp()) / 60;

                                CommonHelpers::closeInternalTrade($openTradeShort->id, $closingTradeData);
                            }
                        }
                    } catch (\Exception $e) {
                        if (!self::$suppressErrors)
                            dd($e);
                        $cmd->error('Error Occured: ', $e->getMessage());
                        Log::error("Failed to update coin reports: " . $e->getMessage());
                    }

                    // Calculate total execution time
                    $endTime = microtime(true);
                    $operationTimes['total'] = $endTime - $startTime;

                    // Calculate percentages of total time
                    $percentages = [];
                    foreach ($operationTimes as $key => $time) {
                        if ($key !== 'total' && $operationTimes['total'] > 0) {
                            $percentages[$key] = round(($time / $operationTimes['total']) * 100, 2);
                        }
                    }

                    // Sort operations by time (descending)
                    arsort($percentages);

                    $perProgress = (($index + 1) / count($timestamps)) * 100;
                    $perProgressTimestamp = (($coinIndex + 1) / count($coins)) * 100;

                    // At the beginning of your processing function, initialize the display once
                    if (!isset($displayInitialized) || $index === 0) {
                        // On first run, send enough newlines to create space for our output
                        echo str_repeat(PHP_EOL, 15); // Adjust number based on total lines needed
                        $displayInitialized = true;
                    }

                    // Save cursor position at start
                    echo "\033[s";
                    // Move cursor up to the start position (adjust number based on total lines of output)
                    echo "\033[15A"; // Move up 15 lines
                    // Clear from cursor to end of screen
                    echo "\033[J";

                    // Now output everything as before, but without the system('clear')
                    $cmd->info("⏳ Processing Data");
                    $cmd->info("-------------------------------");
                    $cmd->info("Parent Loop (Timestamps):");
                    $cmd->info("  → Timestamp: $timestamp");
                    $cmd->info("  → Progress : " . round($perProgress, 2) . " %");
                    $cmd->info("");
                    $cmd->info("Child Loop (Coins):");
                    $cmd->info("  → Symbol   : {$coin->symbol}");
                    $cmd->info("  → Progress : " . round($perProgressTimestamp, 2) . " %");
                    $cmd->info("-------------------------------");
                    $cmd->info("");
                    $cmd->info("⏱️ Timing Analysis:");
                    $cmd->info("  → Total execution time: " . round($operationTimes['total'] * 1000, 2) . " ms");

                    $cmd->info("  → Operation breakdown:");
                    foreach ($percentages as $operation => $percentage) {
                        $timeMs = round($operationTimes[$operation] * 1000, 2);
                        $cmd->info("     • " . str_pad(ucwords(str_replace('_', ' ', $operation)) . ":", 25) . " $timeMs ms ($percentage%)");
                    }
                    $cmd->info("-------------------------------");

                    // Restore cursor position
                    echo "\033[u";
                }
            } catch (\Exception $e) {
                if (!self::$suppressErrors)
                    dd($e);
                $cmd->error('Error Occured: ', $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }

            // Update progress in DB
            $perProgress = (($index + 1) / count($timestamps)) * 100;
            DB::table('formula_details')->where('formula', self::$formula)->update([
                'progress' => $perProgress,
            ]);
            // ############################################################################################################
        }


       
        return true;
    }







    // Function to check opening Conditions

    public static function handleOpeningConditionsLong($symbol, $data, $index, $supportResistance, $orderBookSnapshot, &$confirmIndexLong)
    {

        if (!$orderBookSnapshot)
            return null;


        $buyLongCondition = false;


        if ($data[$index]['per'] > 0.08 && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {

            $loopIndex = $index;
            while ($data[$loopIndex]['histogram'] < 0 || $loopIndex == $index) {



                $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $loopIndex);

                if (!$orderBookSnapshotLoop) {
                    break;
                }
                $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
                $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;


                $macdLongConditionLoop =
                    $data[$loopIndex]['histogram'] > $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] < 0
                    && $data[$loopIndex - 1]['histogram'] < $data[$loopIndex - 2]['histogram']
                    && $data[$loopIndex - 2]['histogram'] < $data[$loopIndex - 3]['histogram']
                    && $data[$loopIndex - 3]['histogram'] < $data[$loopIndex - 4]['histogram']
                    && $data[$loopIndex - 4]['histogram'] < $data[$loopIndex - 5]['histogram'];

                $buyLongConditionInitial =  $imbalance > 5 && $spread_pct < 0.1
                    && $data[$index]['obv'] > $data[$index - 1]['obv']
                    && $data[$index]['rsi6'] > 18 && $data[$index - 1]['rsi6'] <= 18
                    && $macdLongConditionLoop && $data[$loopIndex]['mfi'] < 30 && $orderBookSnapshot->volume_imbalance > 1
                    && $data[$loopIndex]['K'] < 30
                    && $data[$loopIndex]['J'] > $data[$loopIndex]['K'] && $data[$loopIndex]['J'] > $data[$loopIndex]['D'];

                $loopIndex--;
                if ($buyLongConditionInitial) {

                    $confirmIndexLong = $index;
                    $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

                    if (
                        $bbAnalysis['long_probability'] == 0
                        && CommonHelpers::getPercentDiff($data[$index - 1]['rsi6'], $data[$index - 1]['rsi6'], true) > 100


                    ) {

                        return 'LONG';
                    }
                    if (

                        $bbAnalysis['long_probability'] >= 45 && $bbAnalysis['short_probability'] == 0

                    ) {

                        return 'LONG';
                    }





                    self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
                    break;
                }
            }
        }


        if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {


            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

            if (


                $data[$index]['per'] > 0

                && $bbAnalysis['is_expanding']
                && $bbAnalysis['percent_b'] >= 50

            ) {
                $buyLongCondition = self::confirmOpening($symbol, 'LONG', $data, $index);
            }
        }



        // Long condition
        if (
            $buyLongCondition

        ) {
            return self::$longEnabled ? 'LONG' : null;
        }


        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditionsLong($symbol, $data, $index, $tradeTypeLong, $openingIndexLong, $open_price_long)
    {

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeTypeLong == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price_long * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndexLong  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_long, $data[$index]['close']) >= self::$stopLoss && $open_price_long < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeTypeLong == 'LONG') {

            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price_long * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if ($index - $openingIndexLong  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_long, $data[$index]['close']) >= self::$stopLoss && $open_price_long > $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }





















    // ############################################# SHORT Sequences ###########################################


    public static function handleOpeningConditionsShort($symbol, $data, $index, $supportResistance, $orderBookSnapshot, &$confirmIndexShort)
    {

        // if (!$orderBookSnapshot)
        //     return null;


        $sellShortCondition = false;


        if ($data[$index]['per'] < -0.08 && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {

            $loopIndex = $index;
            while ($data[$loopIndex]['histogram'] > 0 || $loopIndex == $index) {




                $macdShortConditionLoop =
                    $data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] > 0
                    && $data[$loopIndex - 1]['histogram'] > $data[$loopIndex - 2]['histogram']
                    && $data[$loopIndex - 2]['histogram'] > $data[$loopIndex - 3]['histogram']
                    && $data[$loopIndex - 3]['histogram'] > $data[$loopIndex - 4]['histogram']
                    && $data[$loopIndex - 4]['histogram'] > $data[$loopIndex - 5]['histogram'];


                $sellShortConditionInitial =

                    $data[$index]['obv'] < $data[$index - 1]['obv']
                    && $macdShortConditionLoop
                    && $data[$index]['mfi'] > 70;

                $loopIndex--;
                if ($sellShortConditionInitial) {
                    $confirmIndexShort = $index;


                    $tightestPointIndex = self::getTightestSqueezIndex($data, $index);
                    if (max($data[$tightestPointIndex]['close'], $data[$tightestPointIndex]['open']) >= max($data[$index]['close'], $data[$index]['open'])) {
                        return null;
                    } else {

                        $candlesAboveMiddleLine = 0;
                        $loopIndex = $index;
                        while (min($data[$loopIndex]['open'], $data[$loopIndex]['close']) > $data[$loopIndex]['bb_middle']) {
                            $candlesAboveMiddleLine++;
                            $loopIndex--;
                        }

                        if ($data[$index]['wr'] < -30) {

                            return null;
                        }

                        if ($data[$index]['stoch_d'] > 80 && $candlesAboveMiddleLine < 15) {
                            return 'SHORT';
                        }


                        // return 'SHORT';
                        self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
                        // return null;
                        break;
                    }
                }
                break;
            }
        }


        if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {



            $bb_squeezed = ($data[$index]['bb_upper'] - $data[$index]['bb_lower']) - ($data[$index - 1]['bb_upper'] - $data[$index - 1]['bb_lower']);


            $candlesAboveMiddleLine = 0;
            $loopIndex = $index;
            while (min($data[$loopIndex]['open'], $data[$loopIndex]['close']) > $data[$loopIndex]['bb_middle']) {
                $candlesAboveMiddleLine++;
                $loopIndex--;
            }
            // Validate point where every condition is true and valid in it timeperiod
            if (
                !($data[$index]['dif'] > $data[$index - 1]['dif'])

                &&
                !($data[$index]['wr'] > -30)

                &&
                $bb_squeezed <= 0

                && $data[$index]['dif'] < max($data[$index - 1]['dif'], $data[$index - 2]['dif'])

                && $candlesAboveMiddleLine < 15

            ) {

                if ($data[$index]['wr'] < -30) {
                    self::confirmOpening($symbol, 'SHORT', $data, $index);
                    return null;
                }

                $sellShortCondition = self::confirmOpening($symbol, 'SHORT', $data, $index);
            }
        }



        // Long condition
        if (
            $sellShortCondition

        ) {
            return 'SHORT';
        }


        // No conditions met so return null
        return null;
    }




    public static function handleClosingConditionsShort($symbol, $data, $index, $tradeTypeShort, $openingIndexShort, $open_price_short)
    {

        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeTypeShort == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price_short * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndexShort  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_short, $data[$index]['close']) >= self::$stopLoss && $open_price_short < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeTypeShort == 'LONG') {
            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price_short * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if ($index - $openingIndexShort  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price_short, $data[$index]['close']) >= self::$stopLoss && $open_price_short > $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }

    // ##############################################################################################################




























    // ######################################### Other Functions ##################################################


    public static function getSupportResistance($data, $index)
    {
        $end = $index + 1; // +1 to include the $index item
        $length = 300;

        $start = max(0, $end - $length); // make sure we donâ€™t go negative
        $slicedData = array_slice($data, $start, $length);

        return MarketTrendService::getCurrentSupportResistanceValueFromData($slicedData, [self::$supportResistanceCandleSpan])[self::$supportResistanceCandleSpan];
    }
    public static function getOrderBookSnapshot($symbol, $data, $index)
    {

        // Fetch OrderBook snapshot
        $timestamp = $data[$index]['timestampReadable'];
        $snapshot = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
            ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
            ->where('symbol', $symbol)
            ->where('depth', 1000)
            ->latest('snapshot_time')
            ->first();
        return $snapshot;
    }


    public static function getFormulaId($formulaName)
    {
        $formula = DB::table('formula_details')->where('formula', $formulaName)->first();
        return $formula ? $formula->id : null;
    }
    public static function getCoinReportsOnFormula($formula_id)
    {
        return  DB::table('coin_reports')
            ->join('formula_details', 'coin_reports.formula', '=', 'formula_details.formula')
            ->where('formula_details.id', $formula_id)
            ->select('coin_reports.*')
            ->get();
    }




    // #########################Functions for confirmed Trades table###############################

    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval, $rounded = true)
    {
        if (!($timestamp1 && $timestamp2)) {
            return false;
        }
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $diff = abs($timestamp1 - $timestamp2) / (60 * 1000 * $intervalToMins);
        return $rounded ? intval($diff) : $diff;
    }


    public static function insertConfirmBasicTradeEntry($symbol, $position, $data, $index)
    {

        $reportId = self::getFormulaId(self::$formula);

        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'report_id' => $reportId,
            'position' => $position,
            'coin_name' => $symbol,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => self::$candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => $bbDiffHighest,
            'bolling_last_squeezed_timestamp' => $data[$highestPointIndex]['binance_timestamp'],
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);
        return DB::table('confirmed_trades')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol, $position)
    {
        $lastEntry =  DB::table('confirmed_trades')->where('coin_name', $symbol)->where('position', $position)->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }


    public static function checkConfirmTradeValidity($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol, $position);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();

        if (!$lastEntry) {
            return null;
        }
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->confirm_candle_timestamp, self::$interval);
        if ($indexDiff > $lastEntry->candles_to_check) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
                'trade_confirmed' => 1,
                'update_time' => Carbon::now()->toDateTimeString(),
            ]);
            return null;
        }
        return $lastEntry;
    }


    public static function confirmOpening($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol, $position);
        if (
            !$ictId
        ) {
            return null;
        }
        DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
            'trade_confirmed' => 1,
            'update_time' => Carbon::now()->toDateTimeString(),
        ]);
        return true;
    }

    public static function getTightestSqueezIndex($data, $startIndex)
    {
        $minSqueeze = CommonHelpers::getPercentDiff(
            $data[$startIndex]['bb_lower'],
            $data[$startIndex]['bb_upper']
        );

        $tightestIndex = $startIndex;
        $currentIndex = $startIndex;

        // Step 1: Loop backward until histogram crosses from red to green
        while ($currentIndex > 0) {
            $currentSqueeze = CommonHelpers::getPercentDiff(
                $data[$currentIndex]['bb_lower'],
                $data[$currentIndex]['bb_upper']
            );

            if ($currentSqueeze < $minSqueeze) {
                $minSqueeze = $currentSqueeze;
                $tightestIndex = $currentIndex;
            }

            // Histogram crossover from red to green
            if (
                $data[$currentIndex]['histogram'] > 0 &&
                $data[$currentIndex - 1]['histogram'] < 0
            ) {
                break;
            }

            $currentIndex--;
        }

        // Step 2: After crossover, check previous 3-entry blocks for tighter squeeze
        while ($currentIndex > 2) {
            $foundSmaller = false;

            for ($i = 1; $i <= 3; $i++) {
                $checkIndex = $currentIndex - $i;
                if ($checkIndex < 0) break;

                $squeeze = CommonHelpers::getPercentDiff(
                    $data[$checkIndex]['bb_lower'],
                    $data[$checkIndex]['bb_upper']
                );

                if ($squeeze < $minSqueeze) {
                    $minSqueeze = $squeeze;
                    $tightestIndex = $checkIndex;
                    $currentIndex = $checkIndex; // Move back to this point
                    $foundSmaller = true;
                }
            }

            // If no tighter squeeze found in last 3, break
            if (!$foundSmaller) {
                break;
            }
        }

        return $tightestIndex;
    }


    public static function getConfirmIndex($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol, $position);
        if (
            !$ictId
        ) {
            return $index;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();

        if (!$lastEntry) {
            return $index;
        }



        $loopIndex = $index;
        while (true) {
            if ($lastEntry->confirm_candle_timestamp == $data[$loopIndex]['binance_timestamp']) {
                return $loopIndex;
            }
            if ($loopIndex < 1) {
                return $index;
            }
            $loopIndex--;
        }
    }
}
