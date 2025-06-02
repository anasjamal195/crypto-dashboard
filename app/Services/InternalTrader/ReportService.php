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
    public static $supportResistanceCandleSpan = 12;

    public static $interval = '5m';
    public static $targetProfit = 0.4;
    public static $stopLoss = 1;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = false;
    public static $earlyClosingEnabled = true;

    // Trend Analysis
    public static $trendReferenceSymbol = 'HBARUSDT';
    public static $trendReferenceInterval = '1h';

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

    public static $candlesToCheck = 1000;
    public static $volumeMA5ValidFor = 1000;
    public static $upperWickValidFor = 1000;
    public static $bollSqueezValidFor = 1000;


    public static $safeModeTimestamp = null;
    public static $lastDisableTime = null;
    public static $isBaseReport = false;

    public static $backTestTimeUnix = 1732561200000;


    // public static $backTestTimeUnix = 1746644400000; // Bullish
    // public static $backTestTimeUnix = 1747925580000; // Bearish




    public static $profitableCoins = [];





    public static $formula = 'Base Report (Flat)';
    // Bearish Base Report
    // public static $baseReportFormula = 'Base Report (Bullish) - Thursday, May 29, 2025 11:35 AM';
    // Bullish Base Report
    public static $baseReportFormula = 'Base Report (Flat) - Monday, June 2, 2025 06:12 PM';

    public static $timeWiseTradesCount = [];
    public static function generateCoinReport(
        $cmd = null
    ) {

        $tradesTotal = [];
        $coinsQuery = DB::table('coins')->where('market', 'FUTURE')->where('status', 'T')


            // ->whereIn(
            //     'symbol',
            //     [
            //         'JTOUSDT'

            //     ]
            // )
        ;


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

        // Clear Console
        system('clear');
        $cmd->info('Processing: 0 %');

        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();


        foreach ($coins as $index => $coin) {

            try {


                $symbol = $coin->symbol;

                Log::info("Test Request Params" . self::$interval);
                $data = BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, self::$backTestTimeUnix, 'FUTURE');

                $trades = self::processCandles($symbol, $data);

                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();
                DB::table('coin_reports')->insert($trades);


                $tradesTotal[$symbol] = $trades;


                $perProgress = (($index + 1) / count($coins)) * 100;
                // system('clear');
                $cmd->info('Processing: ' . round($perProgress) . ' %');
                DB::table('formula_details')->where('formula', self::$formula)->update([
                    'progress' => $perProgress,
                ]);
            } catch (\Exception $e) {
                // dd($e);
                $cmd->error('Error Occured: ', $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
    }

    public static function addFormulaDetails()
    {
        self::$formula = self::$formula . ' - ' . Carbon::now()->format('l, F j, Y h:i A');
        $date = date('Y-m-d H:i:s');

        $dateRange = null;
        $startUnix = null;
        $endUnix = null;
        $startDateStr = null;
        $endDateStr = null;


        if (!self::$backTestTimeUnix) {
            self::$backTestTimeUnix = time() * 1000 - (CommonHelpers::$binanceIntervals[self::$interval] * 60 * 1000 * 1000);
        }


        if (self::$backTestTimeUnix) {
            $diffInMins = CommonHelpers::$binanceIntervals[self::$interval];

            $startUnix = self::$backTestTimeUnix;
            $endUnix = self::$backTestTimeUnix + ($diffInMins * 60 * 1000 * 1000);

            // Get current time in milliseconds
            $currentUnixMillis = round(microtime(true) * 1000);

            // If end time is in the future, use current time instead
            if ($endUnix > $currentUnixMillis) {
                $endUnix = $currentUnixMillis;
            }

            // Convert milliseconds to seconds for formatting
            $startDateStr = date('F j, Y', $startUnix / 1000);
            $endDateStr = date('F j, Y', $endUnix / 1000);

            $dateRange = $startDateStr . ' to ' . $endDateStr;
        }



        self::$timeWiseTradesCount = self::getTimestampWiseProfitableTrades(self::$baseReportFormula, $endUnix);

        self::$profitableCoins = self::getProfitableCoins(self::$baseReportFormula, $endUnix);






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
                                <td><i class="tim-icons icon-calendar-60"></i> <strong>Date Range:</strong></td>
                                <td>' . ($dateRange ?? 'Not set') . '</td>
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



        $reportConfig = [
            'delayMs' => self::$delayMs,
            'supportResistanceCandleSpan' => self::$supportResistanceCandleSpan,
            'backTestTimeUnix' => self::$backTestTimeUnix,
            'interval' => self::$interval,
            'targetProfit' => self::$targetProfit,
            'stopLoss' => self::$stopLoss,
            'stopLossWaitingDuration' => self::$stopLossWaitingDuration,
            'longEnabled' => self::$longEnabled,
            'shortEnabled' => self::$shortEnabled,
            'formula' => self::$formula,
            'earlyClosingEnabled' => self::$earlyClosingEnabled,
            'startUnix' => $startUnix,
            'endUnix' => $endUnix,
            'startDateStr' => $startDateStr,
            'endDateStr' => $endDateStr,
            'dateRange' => $dateRange,
            'trendReferenceSymbol' => self::$trendReferenceSymbol,
            'trendReferenceInterval' => self::$trendReferenceInterval,

            // Coin Selection Filters
            'coinLimit' => self::$coinLimit,
            'shuffleCoins' => self::$shuffleCoins,

            'filterOnCoinType' => self::$filterOnCoinType,
            'coinTypeMetaverse' => self::$coinTypeMetaverse,
            'coinTypeAlt' => self::$coinTypeAlt,
            'coinTypeMeme' => self::$coinTypeMeme,
            'coinTypeDefi' => self::$coinTypeDefi,
            'coinTypeNft' => self::$coinTypeNft,
            'coinTypeWeb3' => self::$coinTypeWeb3,
        ];

        DB::table('formula_details')->insert([
            'formula' => self::$formula,
            'details' => $html,
            'report_config' => json_encode($reportConfig),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }


    protected static function processCandles($symbol, $data)
    {
        $open_price = 0;

        $tradeType = null;


        $currentTrade = [];
        $trades = [];

        $extremePrice = 0;


        $intervalToMins = CommonHelpers::$binanceIntervals[self::$interval];
        $timestamp = $data[0]['binance_timestamp'] - (60 * $intervalToMins * 1000 * 1000);
        $averageAdjustmetCandles =  BinanceApiService::getCandleStickData($symbol, self::$interval, 1000, $timestamp, 'FUTURE');

        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($averageAdjustmetCandles, $data));

        $waitingCandles = 0;
        $openingIndex = 0;
        self::$safeModeTimestamp = null;

        $safeModeEnableTimestamps = [];
        $safeModeDisabledTimestamps = [];

        foreach ($data as $index => $candle) {



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

            // Skip Adjustment Candles and Volume Adjustment
            if ($index < 1200) {
                continue;
            }

            // 20 mins weight after each trade

            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }
            $supportResistance = self::getSupportResistance($data, $index);
            $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);

            if ($open_price == 0) {

                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades, $safeModeEnableTimestamps, $safeModeDisabledTimestamps);






                if (
                    $tradeType
                ) {


                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                    $candle['openingVolumes'] = json_encode($volumeSignal);

                    $open_price = $candle['close'];



                    // Get Trend Details




                    $candle['trendDetails'] = json_encode(CommonHelpers::detectTrend($data, $index, 50, 50));
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $currentTrade['previousCandle'] = json_encode($data[$index - 1]);
                    $extremePrice = $open_price;
                    // Placeholder object for testing

                    $openingIndex = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditions($symbol, $data, $index,  $tradeType, $openingIndex, $open_price);

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

                    $candle['closingVolumes'] = json_encode($volumeSignal);

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




                    error_log("LONG Entry for {$symbol}: " . $currentTrade['profit']);

                    if ($currentTrade['profit'] < 0) {


                        // dd(self::getTradeStatsFromReport(self::$baseReportFormula, $data[$openingIndex]['binance_timestamp']));
                    }

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


        self::confirmOpening($symbol, 'LONG', $data, $index);
        self::confirmOpening($symbol, 'SHORT', $data, $index);

        self::logSafeModeEntry(self::$formula, $symbol, $safeModeEnableTimestamps, $safeModeDisabledTimestamps);
        // For shifting indexes
        $data_new = [];
        foreach ($data as $d) {
            $data_new[] = $d;
        }
        // dd($data_new);
        $data = $data_new;

        // dd("Test");
        return $trades;
    }



    // Function to check opening Conditions

    public static function handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades, &$safeModeEnableTimestamps, &$safeModeDisabledTimestamps)
    {



        // Long Conditions
        if (self::$longEnabled) {





            $skippingReasons = [];



            // $allowedSymbolsList = self::parseProfitableSymbols($data[$index]['binance_timestamp'], 12);
            // if (!in_array($symbol, $allowedSymbolsList)) {
            //     error_log("Skipping... $symbol symbol not in list " . implode(' ',$allowedSymbolsList));

            //     return null;
            // }


            if (!self::$isBaseReport) {
                $currentAccuracy = self::parseAccuracy($data[$index]['binance_timestamp']);

                // error_log(json_encode($currentAccuracy));

                if ($currentAccuracy != -1) {


                    if ($currentAccuracy < 75) {

                        // error_log("Safe Mode Enabled on $symbol at index: $index accuracy: " . $currentAccuracy);

                        // self::$safeModeTimestamp = $data[$index]['binance_timestamp'];
                        // error_log("Skipping... $symbol due to low accuracy " . $currentAccuracy['accuracy'] );
                        return null;
                    }
                }
            }

            if ($data[$index]['rsi6'] < 30 && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {
                self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
            }

            if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {

                $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
                $buyCondition = $data[$index]['close'] > $data[$index]['bb_lower']
                    && $data[$index]['open'] < $data[$index]['bb_lower']
                    && $data[$index]['stoch_d'] > $data[$index - 1]['stoch_d']
                    && $data[$index]['stoch_k'] > $data[$index - 1]['stoch_k']
                    && $bbAnalysis['price_action']['is_near_lower_band']
                    && !$bbAnalysis['bb_squeeze']
                    && $data[$index]['histogram'] > $data[$index - 1]['histogram'];



                if (!self::$isBaseReport) {

                    // if (!self::$safeModeTimestamp) {
                    //     $tradeSummary = self::getTradeStatsFromReport(self::$baseReportFormula, $data[$index]['binance_timestamp'], 24);
                    //     if ($tradeSummary['profitableTradesFiltered']  == 0) {
                    //         self::$safeModeTimestamp = $data[$index]['binance_timestamp'];
                    //         $skippingReasons[9] = 'safe mode enabled on current candle';

                    //         $timestamp = $data[$index]['timestampReadable'];
                    //         error_log("Safe Mode Enabled on $symbol at index: $index at time $timestamp : ProfitableTrades: " . $tradeSummary['profitableTradesFiltered']);
                    //         // unset($tradeSummary['trades']);
                    //         // error_log("Raw data: " . json_encode($tradeSummary));
                    //     }
                    // }


                    if (self::$safeModeTimestamp) {

                        $safeModeStartTime = end($safeModeEnableTimestamps);
                        $indexDiff = self::getIndexDiffFromTimestamps($safeModeStartTime, $data[$index]['binance_timestamp'], self::$interval, true);

                        $safeModeStartIndex = $index - $indexDiff;
                        $lowestClosePrice = $data[$safeModeStartIndex]['close'];

                        for ($i = $safeModeStartIndex; $i <= $index; $i++) {
                            if ($lowestClosePrice > $data[$i]['close']) {
                                $lowestClosePrice = $data[$i]['close'];
                            }
                        }
                        $percentDiffFromLowest = CommonHelpers::getPercentDiff($lowestClosePrice, $data[$index]['close'], true);
                        $safeModeDisableConditions = (
                            $data[$index]['close'] > $supportResistance['resistance']
                            // && $data[$index]['open'] > $supportResistance['resistance']
                            // $percentDiffFromLowest >= 3
                        );
                        if (self::$safeModeTimestamp && $safeModeDisableConditions) {
                            self::$lastDisableTime = $data[$index]['binance_timestamp'];
                            self::$safeModeTimestamp = null;
                            $timestamp = $data[$index]['timestampReadable'];
                            $safeModeDisabledTimestamps[] = $data[$index]['binance_timestamp'];
                            error_log("Safe Mode Disabled on $symbol at index:  $index at time $timestamp");
                        }
                    }
                }





                if ($buyCondition) {
                    self::confirmOpening($symbol, 'LONG', $data, $index);

                    $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'LONG', $data, $index);

                    if ($allowOnHigherTrend) {




                        if (!self::$isBaseReport) {
                            if (self::$safeModeTimestamp) {
                                $skippingReasons[8] = 'safe mode enabled';
                                self::insertSkippedTradesEntry($symbol, $data, $index, 'LONG', $skippingReasons);
                                $safeModeEnableTimestamps[] = $data[$index]['binance_timestamp'];
                                return null;
                            }
                        }

                        // error_log("Profitable (Base Report) {$symbol}: " . $tradeSummary['profitableTradesFiltered']);
                        // error_log("Loss (Base Report) {$symbol}: " . $tradeSummary['lossTradesFiltered']);
                        return 'LONG';
                    } else {
                        $skippingReasons[10] = '1h-candle trend rejected';
                        self::insertSkippedTradesEntry($symbol, $data, $index, 'LONG', $skippingReasons);
                    }
                }
            }

            // Skipping Conditions
            // if (!$buyCondition) {
            //     if (!($data[$index]['close'] > $data[$index]['bb_lower'])) {
            //         $skippingReasons[1] = '5m-candle closing below bb_lower';
            //     }

            //     if (!($data[$index]['open'] < $data[$index]['bb_lower'])) {
            //         $skippingReasons[2] = '5m-candle opening above bb_lower';
            //     }
            //     if (!($data[$index]['stoch_d'] > $data[$index - 1]['stoch_d'])) {
            //         $skippingReasons[3] = '5m-candle stoch_d not increasing';
            //     }

            //     if (!($data[$index]['stoch_k'] > $data[$index - 1]['stoch_k'])) {
            //         $skippingReasons[4] = '5m-candle stoch_k not increasing';
            //     }
            //     if (!($bbAnalysis['price_action']['is_near_lower_band'])) {
            //         $skippingReasons[5] = '5m-candle not near bb lower band';
            //     }
            //     if (!(!$bbAnalysis['bb_squeeze'])) {
            //         $skippingReasons[6] = '5m-candle bb is squeezed already';
            //     }
            //     if (!($data[$index]['histogram'] > $data[$index - 1]['histogram'])) {
            //         $skippingReasons[7] = '5m-candle MACD not increasing';
            //     }

            //     self::insertSkippedTradesEntry($symbol, $data, $index, 'LONG', $skippingReasons);
            // }
        }


        // Short Conditions
        if (self::$shortEnabled) {

            if ($data[$index]['rsi6'] > 70 && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {
                self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
            }

            if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {

                $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
                $sellCondition =

                    $data[$index]['close'] < $data[$index]['bb_upper']
                    && $data[$index]['open'] > $data[$index]['bb_upper']
                    && $data[$index]['stoch_d'] < $data[$index - 1]['stoch_d']
                    && $data[$index]['stoch_k'] < $data[$index - 1]['stoch_k']
                    && $bbAnalysis['price_action']['is_near_upper_band']
                    && !$bbAnalysis['bb_squeeze']
                    && $data[$index]['histogram'] < $data[$index - 1]['histogram']
                    && $data[$index - 1]['stoch_d'] < 100
                    && $data[$index - 1]['stoch_k'] < 100;


                if ($sellCondition) {
                    self::confirmOpening($symbol, 'SHORT', $data, $index);
                    $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'SHORT', $data, $index, '30m');
                    if ($allowOnHigherTrend) {
                        return 'LONG';
                    } else {

                        DB::table('skipped_trades')->insert([
                            'symbol' => $symbol . '( LONG )',
                            'start_time' => $data[$index]['timestampReadable'],
                            'end_time' => $index < 999 ? $data[$index + 1]['timestampReadable'] : $data[$index]['timestampReadable'],
                            'color' => 'orange',
                            'formula' => self::$formula,
                            'position' => 'LONG',
                            'buyingCandle' => json_encode($data[$index]),
                            'sellingCandle' => $index < 999 ? json_encode($data[$index + 1]) : json_encode($data[$index]),
                        ]);
                    }
                }
            }
        }


        return null;
    }


    public static function getTradeStatsFromReport($formula, $binance_timestamp, $filterHours = 4, $lengthThresholdMins = 30)
    {

        $profitable = $loss = 0;

        $profitableTradesFilterHour = 0;
        $lossTradesFilterHour = 0;
        $lengthyTradesFilterHour = 0;
        $filterHoursStartTime = $binance_timestamp - ($filterHours * 60 * 60 * 1000);

        $lengthyTrades = 0;


        $totalProfitable = 0;

        foreach (self::$timeWiseTradesCount as $timestampObj) {
            $timestamp = $timestampObj->buying_timestamp;
            if ($timestamp >= $filterHoursStartTime && $timestamp <= $binance_timestamp) {
                $totalProfitable++;
            }
        }

        // Testing Report
        $tradeStats = [
            'profitableTradesFiltered' => $totalProfitable,
        ];

        return $tradeStats;











        $trades = DB::table('coin_reports')
            ->where('formula', $formula)
            ->whereNotNull('sellingCandle')
            ->whereRaw("JSON_EXTRACT(sellingCandle, '$.binance_timestamp') < ?", [$binance_timestamp])
            ->whereRaw("JSON_EXTRACT(buyingCandle, '$.binance_timestamp') > ?", [$filterHoursStartTime])
            ->where('profit', '>', 0)
            ->count();

        // foreach ($trades as $trade) {

        //     $tradeTimestamp = json_decode($trade->buyingCandle, true)['binance_timestamp'];
        //     if ($trade->profit > 0) {

        //         if ($tradeTimestamp >= $filterHoursStartTime) {
        //             $profitableTradesFilterHour++;
        //         }

        //         if ($trade->duration >= $lengthThresholdMins) {
        //             $lengthyTradesFilterHour++;
        //         }
        //         $profitable++;
        //     } else {
        //         if ($tradeTimestamp >= $filterHoursStartTime) {
        //             $lossTradesFilterHour++;
        //         }
        //         $loss++;
        //     }


        //     if ($trade->duration >= $lengthThresholdMins) {
        //         $lengthyTrades++;
        //     }
        // }

        $tradeStats = [
            'trades' => $trades,
            // 'totalTrades' => count($trades),
            'totalProfitable' => $profitable,
            'totalLoss' => $loss,
            'lengthyTrades' => $lengthyTrades,
            'lengthThresholdMins' => $lengthThresholdMins,
            // 'profitableTradesFiltered' => $profitableTradesFilterHour,
            'profitableTradesFiltered' => $trades,
            'lossTradesFiltered' => $lossTradesFilterHour,
            'lengthyTradesFiltered' => $lengthyTradesFilterHour,
            'filterTimeInHours' => $profitableTradesFilterHour,
        ];

        return $tradeStats;
    }

    public static function getTimestampWiseProfitableTrades($formula, $binance_timestamp)
    {
        $trades = DB::table('coin_reports')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp')) as buying_timestamp, COUNT(*) as trade_count")
            ->where('formula', $formula)
            ->whereNotNull('sellingCandle')
            ->whereRaw("JSON_EXTRACT(sellingCandle, '$.binance_timestamp') <=  ?", [$binance_timestamp])
            ->where('profit', '>', 0)
            ->groupBy(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp'))"))
            ->orderBy('buying_timestamp')
            ->get()
            ->toArray();

        return $trades;
    }



    public static function getProfitableCoins($formula, $binance_timestamp)
    {


        $rawData = DB::table('coin_reports')
            ->selectRaw("
        JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp')) as buying_timestamp,
        symbol,
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as profitable_trades,
        SUM(CASE WHEN profit <= 0 THEN 1 ELSE 0 END) as loss_trades,
        ROUND((SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as accuracy
    ")
            ->where('formula', $formula)
            ->whereNotNull('sellingCandle')
            ->whereRaw("JSON_EXTRACT(sellingCandle, '$.binance_timestamp') <= ?", [$binance_timestamp])
            ->groupBy(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp'))"),
                'symbol'
            )
            ->orderBy('buying_timestamp', 'ASC')
            ->get();
        $grouped = [];

        foreach ($rawData as $row) {
            $timestamp = $row->buying_timestamp;

            if (!isset($grouped[$timestamp])) {
                $grouped[$timestamp] = [
                    'timestamp' => $timestamp,
                    'total_profit' => 0,
                    'total_loss' => 0,
                    'accuracy' => 0,
                    'high_accuracy_symbols' => [],
                ];
            }

            $grouped[$timestamp]['total_profit'] += $row->profitable_trades;
            $grouped[$timestamp]['total_loss'] += $row->loss_trades;

            if ($row->accuracy > 90) {
                $grouped[$timestamp]['high_accuracy_symbols'][] = $row->symbol;
            }
        }

        // Now calculate overall accuracy per timestamp
        foreach ($grouped as &$item) {
            $totalTrades = $item['total_profit'] + $item['total_loss'];
            $item['accuracy'] = $totalTrades > 0
                ? round(($item['total_profit'] / $totalTrades) * 100, 2)
                : 0;
        }


        return $grouped;
    }



    public static function parseProfitableSymbols($groupedArray,$endTime, $hours = 5)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);

        $timestampEntry = null;
        foreach (array_reverse(self::$profitableCoins) as $key => $timestampDetail) {

            if ($key >= $filterHoursStartTime && $key <= $endTime) {
                $timestampEntry = $timestampDetail;
                break;
            }
        }


        if ($timestampEntry) {

            return $timestampEntry['high_accuracy_symbols'];
        }

        return [];
    }





    public static function parseAccuracy($endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);

        $grouped = self::$profitableCoins;
        $latest = null;

        if (!$hours) {
            $filterHoursStartTime = 0;
        }


        $totalProfits = 0;
        $totalLosses = 0;

        foreach ($grouped as $timestamp => $data) {
            if ($timestamp <= $endTime && $timestamp >= $filterHoursStartTime) {
                $totalLosses += $data['total_loss'];
                $totalProfits += $data['total_profit'];
            }
        }



        $totalTrades = $totalProfits + $totalLosses;
        return $totalTrades != 0 ? ($totalProfits / $totalTrades) * 100 : -1;
    }

    public static function handleClosingConditions($symbol, $data, $index, $tradeType, $openingIndex, $open_price)
    {
        $candle = $data[$index];
        $closingPrice = 0;
        $waitingCandlesBeforeStopLoss = intval(self::$stopLossWaitingDuration / CommonHelpers::$binanceIntervals[self::$interval]);
        if ($tradeType == 'SHORT') {
            // Calculate Closing in profit 
            if ($candle['low'] <= $open_price * (1 - self::$targetProfit / 100)) {
                $closingPrice = $candle['low'];
            } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price < $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        } else if ($tradeType == 'LONG') {

            // Calculate Closing in profit 
            if ($candle['high'] >= $open_price * (1 + self::$targetProfit / 100)) {
                $closingPrice = $candle['high'];
            } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price > $data[$index]['close']) {
                $closingPrice = $data[$index]['close'];
            }
        }


        return $closingPrice;
    }



    public static function insertSkippedTradesEntry($symbol, $data, $index, $position, $reasons = [])
    {
        DB::table('skipped_trades')->insert([
            'symbol' => $symbol . '( ' . $position . ' )',
            'start_time' => $data[$index]['timestampReadable'],
            'end_time' => $index < (count($data) - 1) ? $data[$index + 1]['timestampReadable'] : $data[$index]['timestampReadable'],
            'color' => 'orange',
            'formula' => self::$formula,
            'position' => $position,
            'buyingCandle' => json_encode($data[$index]),
            'sellingCandle' => $index < (count($data) - 1) ? json_encode($data[$index + 1]) : json_encode($data[$index]),
            'skipping_reasons' => json_encode($reasons),
        ]);
    }

    public static  function logSafeModeEntry($formula, $symbol, $enableTimestamps, $disableTimestamps)
    {
        return DB::table('safe_mode_logs')->insert([
            'formula' => $formula,
            'symbol' => $symbol,
            'enable_timestamps' => is_array($enableTimestamps) ? json_encode($enableTimestamps) : $enableTimestamps,
            'disable_timestamps' => is_array($disableTimestamps) ? json_encode($disableTimestamps) : $disableTimestamps,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }



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


    // #########################Functions for confirmed Trades table###############################

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



        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'coin_name' => $symbol,
            'position' => $position,
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

        DB::table('confirmed_trades')->where('coin_name', $symbol)->where('position', $position)->orderBy('update_time', 'DESC')->delete();
        return true;
    }



    public static function checkTrendOnHigherCandles($symbol, $position, $data, $index, $higherInterval = '1h')
    {



        $dataHigher = BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE');
        $indexHigher = count($dataHigher) - 2;

        if ($position === 'LONG') {
            $loopIndex = $indexHigher;
            $crossOverCondition = false;
            $bbMiddleCondition = $dataHigher[$indexHigher]['bb_middle'] <= $dataHigher[$indexHigher - 1]['bb_middle'];

            // Check Last Crossover for dif dea
            while ($loopIndex > 0) {

                $difCurrent = $dataHigher[$loopIndex]['dif'];
                $deaCurrent = $dataHigher[$loopIndex]['dea'];

                $difPrev = $dataHigher[$loopIndex - 1]['dif'];
                $deaPrev = $dataHigher[$loopIndex - 1]['dea'];


                // Dif Crossing DEA from above
                if ($difCurrent < $deaCurrent && $difPrev >= $deaPrev) {
                    // if ($difCurrent > 0 && $deaCurrent > 0)
                    $crossOverCondition = true;
                    // else
                    // $crossOverCondition = false;
                    break;
                }
                // Dif Crossing DEA from below
                else if ($difCurrent > $deaCurrent && $difPrev <= $deaPrev) {
                    $crossOverCondition = false;
                    break;
                }

                $loopIndex--;
            }

            return !($crossOverCondition && $bbMiddleCondition);
        } else {
            $loopIndex = $indexHigher;
            $crossOverCondition = false;
            $bbMiddleCondition = $dataHigher[$indexHigher]['bb_middle'] >= $dataHigher[$indexHigher - 1]['bb_middle'];

            // Check Last Crossover for dif dea
            while ($loopIndex > 0) {

                $difCurrent = $dataHigher[$loopIndex]['dif'];
                $deaCurrent = $dataHigher[$loopIndex]['dea'];

                $difPrev = $dataHigher[$loopIndex - 1]['dif'];
                $deaPrev = $dataHigher[$loopIndex - 1]['dea'];


                // Dif Crossing DEA from above
                if ($difCurrent < $deaCurrent && $difPrev >= $deaPrev) {
                    // if ($difCurrent > 0 && $deaCurrent > 0)
                    $crossOverCondition = false;
                    // else
                    // $crossOverCondition = false;
                    break;
                }
                // Dif Crossing DEA from below
                else if ($difCurrent > $deaCurrent && $difPrev <= $deaPrev) {
                    $crossOverCondition = true;
                    break;
                }

                $loopIndex--;
            }

            return !(($crossOverCondition && $bbMiddleCondition));
        }
    }
}
