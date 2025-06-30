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
use App\Services\SupportResistanceAnalyzer;
use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Support\Facades\File;

class ReportService
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 12;

    public static $interval = '5m';
    public static $targetProfit = 1;
    public static $stopLoss = 0.8;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = true;
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
    public static $isBaseReport;

    public static $backTestTimeUnix;


    // public static $backTestTimeUnix = 1746644400000; // Bullish
    // public static $backTestTimeUnix = 1747925580000; // Bearish




    public static $progressionDetailsLONG = [];

    public static $progressionDetailsLONGMACD = [];
    public static $progressionDetailsLONGSR = [];


    public static $progressionDetailsSHORT = [];

    public static $progressionDetailsSHORTMACD = [];
    public static $progressionDetailsSHORTSR = [];


    public static $formula;
    public static $baseReportFormula;
    public static $timeWiseTradesCount = [];

    public static $formulaMACD;
    public static $formulaSR;
    public static $baseReportFormulaMACD;
    public static $baseReportFormulaSR;

    public static $dynamicTP = 0;
    public static $dynamicSL = 0;

    public static $dynamicTPSLgap = 0.2;

    public static $initialTpPercent = 0.5;
    public static $initialSlPercent = 1;



    public static $lows = [];
    public static $highs = [];

    public static function generateCoinReport(
        $cmd = null,
        $formula = 'Default',
        $timestamp = null,
        $baseReportFormula = null,
        $baseReport = true
    ) {


        self::$formula = $formula;
        self::$backTestTimeUnix = $timestamp;
        self::$isBaseReport = $baseReport;
        self::$baseReportFormula = $baseReportFormula;





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
                dd($e);
                $cmd->error('Error Occured: ', $e->getMessage());
                Log::error("Failed to update coin reports: " . $e->getMessage());
            }
            CommonHelpers::delayMS(self::$delayMs);
        }

        $cmd->info('Completed Report for : ' . self::$formula);
        $cmd->info('Total Coins Processed : ' . count($coins));
        return self::$formula;
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

        self::$progressionDetailsLONGMACD = self::getProgressionDetails(self::$baseReportFormula, 'LONG', $endUnix, 'MACD');
        self::$progressionDetailsLONGSR = self::getProgressionDetails(self::$baseReportFormula, 'LONG', $endUnix, 'SR');

        self::$progressionDetailsSHORTMACD = self::getProgressionDetails(self::$baseReportFormula, 'SHORT', $endUnix, 'MACD');
        self::$progressionDetailsSHORTSR = self::getProgressionDetails(self::$baseReportFormula, 'SHORT', $endUnix, 'SR');


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
            'exchange' => 'binance',

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


            // Highs and Lows Calculation


            $pivot = CommonHelpers::checkPivot($data, $index, 5);
            $minDistanceBetweenPivots = 10;
            if ($pivot === 'high_pivot') {
                // Only add if it's far enough from the last high
                if (empty(self::$highs) || ($index - end(self::$highs)) >= $minDistanceBetweenPivots) {
                    self::$highs[] = $index;
                }
            } else if ($pivot === 'low_pivot') {
                // Only add if it's far enough from the last low
                if (empty(self::$lows) || ($index - end(self::$lows)) >= $minDistanceBetweenPivots) {
                    self::$lows[] = $index;
                }
            }



            $supportResistance = self::getSupportResistance($data, $index);
            $orderBookSnapshot = self::getOrderBookSnapshot($symbol, $data, $index);

            if ($open_price == 0) {

                $tagName = null;
                $tradeType = self::handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades, $safeModeEnableTimestamps, $safeModeDisabledTimestamps, $tagName);





                if (
                    $tradeType
                ) {



                    $candle['should_buy'] = true;
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['orderBookSnapshot'] = $orderBookSnapshot ? $orderBookSnapshot->id : null;
                    $candle['openingVolumes'] = json_encode($volumeSignal);


                    if (!self::$isBaseReport) {
                        $candle['macd_frequency_long'] = self::parseFrequency(self::$progressionDetailsLONGMACD, $data[$index]['binance_timestamp'], 6);
                        $candle['macd_frequency_short'] = self::parseFrequency(self::$progressionDetailsSHORTMACD, $data[$index]['binance_timestamp'], 6);

                        $candle['sr_frequency_long'] = self::parseFrequency(self::$progressionDetailsLONGSR, $data[$index]['binance_timestamp'], 6);
                        $candle['sr_frequency_short'] = self::parseFrequency(self::$progressionDetailsSHORTSR, $data[$index]['binance_timestamp'], 6);


                        $candle['macd_accuracy_long'] = self::parseAccuracy(self::$progressionDetailsLONGMACD, $data[$index]['binance_timestamp'], 6);
                        $candle['macd_accuracy_short'] = self::parseAccuracy(self::$progressionDetailsSHORTMACD, $data[$index]['binance_timestamp'], 6);

                        $candle['sr_accuracy_long'] = self::parseAccuracy(self::$progressionDetailsLONGSR, $data[$index]['binance_timestamp'], 6);
                        $candle['sr_accuracy_short'] = self::parseAccuracy(self::$progressionDetailsSHORTSR, $data[$index]['binance_timestamp'], 6);
                    }



                    $open_price = $candle['close'];





                    // Get Trend Details



                    $candle['trendDetails'] = json_encode(CommonHelpers::detectTrend($data, $index, 50, 50));
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $currentTrade['previousCandle'] = json_encode($data[$index - 1]);
                    $currentTrade['tagName'] = $tagName;
                    $currentTrade['openingTimestamp'] = $data[$index]['binance_timestamp'];


                    $extremePrice = $open_price;
                    // Placeholder object for testing
                    $openingIndex = $index;

                    if ($tradeType === 'LONG') {
                        self::$dynamicTP = $data[$index]['close'] * (1 + self::$initialTpPercent / 100);
                        self::$dynamicSL = $data[$index]['close'] * (1 - self::$initialSlPercent / 100);
                        // dd(self::$dynamicTP, self::$dynamicSL, $data[$openingIndex], $symbol);
                    } else {
                        self::$dynamicTP = $data[$index]['close'] * (1 - self::$initialTpPercent / 100);
                        self::$dynamicSL = $data[$index]['close'] * (1 + self::$initialSlPercent / 100);
                    }
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




                    error_log("$tradeType Entry for {$symbol}: " . $currentTrade['profit']);

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

                    self::$dynamicTP = 0;
                    self::$dynamicSL = 0;
                    self::$candlesToCheck = 1000;
                }
            }
        }


        self::confirmOpening($symbol, 'TBD', $data, $index, 'TBD');

        self::$lows = [];
        self::$highs = [];

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

    public static function handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades, &$safeModeEnableTimestamps, &$safeModeDisabledTimestamps, &$tagName)
    {

        // LONG Entry
        $macdLong = self::checkConditionSetLongMACD($symbol, $data, $index);
        if ($macdLong) {
            $tagName = 'MACD';
            return $macdLong;
        }

        // else if (self::checkConditionSetLongSR($symbol, $data, $index) === 'LONG') {
        //     $tagName = 'SR';
        //     return 'LONG';
        // }



        // SHORT Entry
        // $macdShort = self::checkConditionSetShortMACD($symbol, $data, $index);
        // if ($macdShort) {
        //     $tagName = 'MACD';
        //     return $macdShort;
        // }

        // else if (self::checkConditionSetShortSR($symbol, $data, $index) === 'SHORT') {
        //     $tagName = 'SR';
        //     return 'SHORT';
        // }

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



    public static function getProgressionDetails($formula, $position, $binance_timestamp, $tagName = null)
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
            ->where('formula', $formula);


        if ($tagName) {
            $rawData->where('tagName', $tagName);
        }

        $rawData = $rawData->where('position', $position)
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




    public static function parseFrequency($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


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




    public static function parseAccuracy($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


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




        // if ($tradeType == 'SHORT') {
        //     // Calculate Closing in profit 
        //     if ($candle['low'] <= $open_price * (1 - self::$targetProfit / 100)) {
        //         $closingPrice = $candle['low'];
        //     } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price < $data[$index]['close']) {
        //         $closingPrice = $data[$index]['close'];
        //     }
        // } else if ($tradeType == 'LONG') {

        //     // Calculate Closing in profit 
        //     if ($candle['high'] >= $open_price * (1 + self::$targetProfit / 100)) {
        //         $closingPrice = $candle['high'];
        //     } else if ($index - $openingIndex  >= $waitingCandlesBeforeStopLoss && CommonHelpers::getPercentDiff($open_price, $data[$index]['close']) >= self::$stopLoss && $open_price > $data[$index]['close']) {
        //         $closingPrice = $data[$index]['close'];
        //     }
        // }



        if ($tradeType === 'LONG') {

            // If TP is triggered
            if ($data[$index]['close'] >= self::$dynamicTP) {
                self::$dynamicTP = $data[$index]['close'] * (1 + self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $data[$index]['close'] * (1 - (self::$dynamicTPSLgap / 2) / 100);
            }
            // If Sl is trigggerd
            else if ($data[$index]['close'] < self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        } else {
            $currentProfit = (($data[$openingIndex]['close'] - $data[$index]['close']) / $data[$openingIndex]['close']) * 100;
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


    public static function insertConfirmBasicTradeEntry($symbol, $type, $data, $index, $intention = null, $candlesToCheck = 1000)
    {




        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'coin_name' => $symbol,
            'type' => $type,
            'intention' => $intention,
            'formula' => self::$formula,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'checkpoint_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => $candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => $bbDiffHighest,
            'bolling_last_squeezed_timestamp' => $data[$highestPointIndex]['binance_timestamp'],
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);




        return DB::table('confirmed_trades')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol, $position, $intention = null)
    {
        $lastEntry =  DB::table('confirmed_trades')->where('coin_name', $symbol)->where('type', $position);

        if ($intention) {
            $lastEntry->where('intention', $intention);
        }
        $lastEntry = $lastEntry->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }
    public static function checkConfirmTradeValidity($symbol, $type, $data, $index, $intention = null)
    {
        $ictId = self::getIctId($symbol, $type, $intention);
        if (
            !$ictId
        ) {
            return null;
        }



        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->checkpoint_timestamp, self::$interval);

        if ($indexDiff > $lastEntry->candles_to_check) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
                'trade_confirmed' => 1,
                'update_time' => Carbon::now()->toDateTimeString(),
            ]);
            return null;
        }
        return $lastEntry;
    }

    public static function updateConfirmTradeCheckpoint($symbol, $type, $data, $index, $intention = null, $candlesToCheck = 1000)
    {
        $ictId = self::getIctId($symbol, $type, $intention);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $newCheckpoint = $lastEntry->checkpoints + 1;
        DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
            'checkpoints' => ($newCheckpoint),
            'intention' => ($intention ?? $lastEntry->intention),
            'checkpoint_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => $candlesToCheck,
            'update_time' => Carbon::now()->toDateTimeString(),
        ]);

        return $newCheckpoint;
    }



    public static function confirmOpening($symbol, $type, $data, $index, $newType = null)
    {


        $entry = DB::table('confirmed_trades')->where('coin_name', $symbol)->where('type', $type)->orderBy('update_time', 'DESC')->update(
            [
                'trade_confirmed' => 1,
                'type' => $newType,
                'openingTimestamp' => $newType != 'TBD' ? $data[$index]['binance_timestamp'] : null,
            ]
        );
        return true;
    }



    public static function checkTrendOnHigherCandles($symbol, $position, $data, $index, $higherInterval = '1h')
    {



        $dataHigher = BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE');
        $indexHigher = count($dataHigher) - 2;

        if ($position === 'LONG') {

            $dataHigher = BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE');
            $indexHigher = count($dataHigher) - 2;

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
    // LONG ENTRY DETECTION FUNCTION
    public static function detectLongEntryWithSR($data, $index, $srAnalysis = null)
    {
        // Safety check
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];
        $prev2 = $data[$index - 2];
        $prev3 = $data[$index - 3];

        // === SUPPORT/RESISTANCE ANALYSIS ===
        $srScore = 0;
        $srConfirmation = false;
        $suggestedSL = null;
        $suggestedTP = null;
        $riskReward = 0;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'buy') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    $suggestedSL = $signal['stop_loss'];
                    $suggestedTP = $signal['take_profit_1'];
                    $riskReward = $signal['risk_reward']['ratio'] ?? 0;
                    break;
                }
            }
        }

        // Analyze support levels for additional confirmation
        $nearSupport = false;
        $supportStrength = 0;
        $supportDistance = 999;

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'support') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $supportDistance = min($supportDistance, $distance);

                    // Check if price is near support (within 0.5%)
                    if ($distance <= 0.005) {
                        $nearSupport = true;
                        $supportStrength = $level['confidence'];

                        // Bonus points for high-volume support touches
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }

                        // Bonus for recent touches
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        // === TECHNICAL INDICATOR ANALYSIS ===

        // 1. Trend Analysis
        $trendScore = 0;

        // Moving Average Bullish Alignment
        if ($current['ma7'] > $current['ma14'] && $current['ma14'] > $current['ma25']) {
            $trendScore += 20;
        }

        // Price position relative to MAs
        if ($current['close'] > $current['ma14']) $trendScore += 10;
        if ($current['close'] > $current['ma25']) $trendScore += 10;

        // Bollinger Band position (near lower band suggests reversal)
        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition < 0.2) $trendScore += 15; // Near lower band
        if ($bbPosition < 0.1) $trendScore += 10; // Very close to lower band

        // 2. Momentum Analysis
        $momentumScore = 0;

        // RSI Analysis
        if ($current['rsi6'] < 30) $momentumScore += 20; // Oversold
        if ($current['rsi6'] < 35 && $current['rsi6'] > $prev1['rsi6']) $momentumScore += 15; // Turning up
        if ($current['rsi6'] > $prev1['rsi6'] && $current['close'] < $prev1['close']) $momentumScore += 10; // Bullish divergence

        // Stochastic Analysis
        if ($current['stoch_k'] < 20 && $current['stoch_d'] < 20) $momentumScore += 15;
        if ($current['stoch_k'] > $prev1['stoch_k'] && $current['stoch_d'] > $prev1['stoch_d']) $momentumScore += 10;

        // Williams %R
        if ($current['wr'] < -80) $momentumScore += 10; // Oversold

        // MACD Analysis
        if ($current['dif'] > $current['dea'] && $current['histogram'] > 0) $momentumScore += 10;
        if ($current['histogram'] > $prev1['histogram']) $momentumScore += 10; // Strengthening momentum

        // 3. Volume Analysis
        $volumeScore = 0;

        // Volume spike confirmation
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;

        // OBV bullish confirmation
        if ($current['obv'] > $prev1['obv']) $volumeScore += 10;
        if ($current['obv'] > $prev2['obv'] && $current['obv'] > $prev3['obv']) $volumeScore += 5;

        // Money Flow Index
        if ($current['mfi'] > 50 && $current['mfi'] > $prev1['mfi']) $volumeScore += 10;

        // 4. Price Action Analysis
        $priceActionScore = 0;

        // Bullish candlestick
        if ($current['close'] > $current['open']) $priceActionScore += 10;

        // Long lower wick (support/buying interest)
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $bodySize = abs($current['close'] - $current['open']);
        if ($lowerWick > $bodySize * 1.5) $priceActionScore += 15;

        // Failed breakdown pattern (bullish reversal)
        if ($current['low'] < $prev1['low'] && $current['close'] > $prev1['close']) $priceActionScore += 20;

        // Higher lows pattern
        if ($current['low'] > $prev1['low'] && $prev1['low'] > $prev2['low']) $priceActionScore += 10;

        // === ADVANCED FILTERS ===

        // Market structure confirmation
        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];

            // Support-heavy environment
            if ($structure['support_count'] > $structure['resistance_count']) {
                $structureScore += 10;
            }

            // Recent support interaction
            if (isset($structure['nearest_support']) && $supportDistance < 0.01) {
                $structureScore += 15;
            }
        }

        // === RISK MANAGEMENT CHECKS ===

        // Volatility filter
        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $highVolatility = $bbWidth > 0.08;

        // VWAP distance filter
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        // Recent strong bearish momentum check
        $recentBearMomentum = ($prev1['close'] < $prev2['close'] * 0.985) &&
            ($prev2['close'] < $prev3['close'] * 0.985);

        // === SCORING SYSTEM ===

        $totalTechnicalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;
        $totalScore = $totalTechnicalScore + ($srScore * 0.8); // Weight S/R analysis

        // === ENTRY CONDITIONS ===

        // Base requirements
        $baseConditionsMet = ($totalTechnicalScore >= 60) && // Strong technical setup
            ($current['close'] > $current['open']) && // Bullish candle
            !$highVolatility && // Reasonable volatility
            !$tooFarFromVWAP && // Near VWAP
            !$recentBearMomentum; // No strong counter-trend

        // Enhanced conditions with S/R
        $enhancedConditionsMet = $baseConditionsMet &&
            ($srConfirmation || $nearSupport) && // S/R confirmation
            ($srScore >= 60); // Minimum S/R confidence

        // === SPECIFIC ENTRY SIGNAL FOR 15M CANDLES ===
        // Target: 1% TP, 0.8% SL
        // RSI turning up from oversold + near support + strong S/R score

        if (
            $data[$index]['rsi6'] >= 30 &&
            $data[$index - 1]['rsi6'] <= 30 &&
            $data[$index]['rsi6'] > $data[$index - 1]['rsi6'] &&
            $nearSupport &&
            $srScore >= 75
        ) {
            return 'LONG';
        }

        return null;
    }

    // SR ANALYSIS FUNCTIONS
    public static function detectShortEntryWithSR($data, $index, $srAnalysis = null)
    {
        // Safety check
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];
        $prev2 = $data[$index - 2];
        $prev3 = $data[$index - 3];

        // === SUPPORT/RESISTANCE ANALYSIS ===
        $srScore = 0;
        $srConfirmation = false;
        $suggestedSL = null;
        $suggestedTP = null;
        $riskReward = 0;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'sell') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    $suggestedSL = $signal['stop_loss'];
                    $suggestedTP = $signal['take_profit_1'];
                    $riskReward = $signal['risk_reward']['ratio'] ?? 0;
                    break;
                }
            }
        }

        // Analyze resistance levels for additional confirmation
        $nearResistance = false;
        $resistanceStrength = 0;
        $resistanceDistance = 999;

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'resistance') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $resistanceDistance = min($resistanceDistance, $distance);

                    // Check if price is near resistance (within 0.5%)
                    if ($distance <= 0.005) {
                        $nearResistance = true;
                        $resistanceStrength = $level['confidence'];

                        // Bonus points for high-volume resistance touches
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }

                        // Bonus for recent touches
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        // === TECHNICAL INDICATOR ANALYSIS ===

        // 1. Trend Analysis
        $trendScore = 0;

        // Moving Average Bearish Alignment
        if ($current['ma7'] < $current['ma14'] && $current['ma14'] < $current['ma25']) {
            $trendScore += 20;
        }

        // Price position relative to MAs
        if ($current['close'] < $current['ma14']) $trendScore += 10;
        if ($current['close'] < $current['ma25']) $trendScore += 10;

        // Bollinger Band position (near upper band suggests reversal)
        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition > 0.8) $trendScore += 15; // Near upper band
        if ($bbPosition > 0.9) $trendScore += 10; // Very close to upper band

        // 2. Momentum Analysis
        $momentumScore = 0;

        // RSI Analysis
        if ($current['rsi6'] > 70) $momentumScore += 20; // Overbought
        if ($current['rsi6'] > 65 && $current['rsi6'] < $prev1['rsi6']) $momentumScore += 15; // Turning down
        if ($current['rsi6'] < $prev1['rsi6'] && $current['close'] > $prev1['close']) $momentumScore += 10; // Bearish divergence

        // Stochastic Analysis
        if ($current['stoch_k'] > 80 && $current['stoch_d'] > 80) $momentumScore += 15;
        if ($current['stoch_k'] < $prev1['stoch_k'] && $current['stoch_d'] < $prev1['stoch_d']) $momentumScore += 10;

        // Williams %R
        if ($current['wr'] > -20) $momentumScore += 10; // Overbought

        // MACD Analysis
        if ($current['dif'] < $current['dea'] && $current['histogram'] < 0) $momentumScore += 10;
        if ($current['histogram'] < $prev1['histogram']) $momentumScore += 10; // Weakening momentum

        // 3. Volume Analysis
        $volumeScore = 0;

        // Volume spike confirmation
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;

        // OBV bearish confirmation
        if ($current['obv'] < $prev1['obv']) $volumeScore += 10;
        if ($current['obv'] < $prev2['obv'] && $current['obv'] < $prev3['obv']) $volumeScore += 5;

        // Money Flow Index
        if ($current['mfi'] < 50 && $current['mfi'] < $prev1['mfi']) $volumeScore += 10;

        // 4. Price Action Analysis
        $priceActionScore = 0;

        // Bearish candlestick
        if ($current['close'] < $current['open']) $priceActionScore += 10;

        // Long upper wick (rejection)
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $bodySize = abs($current['close'] - $current['open']);
        if ($upperWick > $bodySize * 1.5) $priceActionScore += 15;

        // Failed breakout pattern
        if ($current['high'] > $prev1['high'] && $current['close'] < $prev1['close']) $priceActionScore += 20;

        // Lower highs pattern
        if ($current['high'] < $prev1['high'] && $prev1['high'] < $prev2['high']) $priceActionScore += 10;

        // === ADVANCED FILTERS ===

        // Market structure confirmation
        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];

            // Resistance-heavy environment
            if ($structure['resistance_count'] > $structure['support_count']) {
                $structureScore += 10;
            }

            // Recent resistance interaction
            if (isset($structure['nearest_resistance']) && $resistanceDistance < 0.01) {
                $structureScore += 15;
            }
        }

        // === RISK MANAGEMENT CHECKS ===

        // Volatility filter
        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $highVolatility = $bbWidth > 0.08;

        // VWAP distance filter
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        // Recent strong bullish momentum check
        $recentBullMomentum = ($prev1['close'] > $prev2['close'] * 1.015) &&
            ($prev2['close'] > $prev3['close'] * 1.015);

        // === SCORING SYSTEM ===

        $totalTechnicalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;
        $totalScore = $totalTechnicalScore + ($srScore * 0.8); // Weight S/R analysis

        // === ENTRY CONDITIONS ===

        // Base requirements
        $baseConditionsMet = ($totalTechnicalScore >= 60) && // Strong technical setup
            ($current['close'] < $current['open']) && // Bearish candle
            !$highVolatility && // Reasonable volatility
            !$tooFarFromVWAP && // Near VWAP
            !$recentBullMomentum; // No strong counter-trend

        // Enhanced conditions with S/R
        $enhancedConditionsMet = $baseConditionsMet &&
            ($srConfirmation || $nearResistance) && // S/R confirmation
            ($srScore >= 60); // Minimum S/R confidence



        // === RETURN SIGNAL ===

        if ($data[$index]['rsi6'] <= 70 && $data[$index - 1]['rsi6'] >= 70 && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] &&  $nearResistance && $srScore >= 75) {
            return 'SHORT';
        }


        return null;
    }









    public static function checkConditionSetLongSR($symbol, $data, $index)
    {
        if (self::$longEnabled) {


            if (!self::$isBaseReport) {
                $currentAccuracy = self::parseAccuracy(self::$progressionDetailsLONGSR, $data[$index]['binance_timestamp'], 6);
                if ($currentAccuracy != -1) {
                    if ($currentAccuracy < 77) {
                        return null;
                    }
                }
            }

            $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
            $srAnalysis = $srAnalyzer->analyze();

            $entry = self::detectLongEntryWithSR($data, $index, $srAnalysis);


            if ($entry === 'LONG') {
                return $entry;
            }
        }
        return null;
    }


    public static function checkConditionSetLongMACD($symbol, $data, $index)
    {
        if (self::$longEnabled) {



            if (!self::$isBaseReport) {
                $currentAccuracy = self::parseAccuracy(self::$progressionDetailsLONGMACD, $data[$index]['binance_timestamp'], 6);
                if ($currentAccuracy != -1) {
                    if ($currentAccuracy < 80) {
                        return null;
                    }
                }
            }


            // ======================================= MULTI STEP Setup for LONG entry =======================================





            $initialSetup = false;


            if (count(self::$lows) >= 2) {
                $recentLowIndex = self::$lows[count(self::$lows) - 1];
                $secondRecentLowIndex = self::$lows[count(self::$lows) - 2];

                if ($index <= ($recentLowIndex + 10)) {
                    return null;
                }

                $recentLow = $data[$recentLowIndex];
                $secondRecentLow = $data[$secondRecentLowIndex];
                $doubleTopTolerance = 0.002;
                $rsiDivergenceThreshold = 2.0; // Minimum RSI difference for divergence

                // Check for Double Bottom Pattern
                $isDoubleBottom = CommonHelpers::checkDoubleBottom($recentLow, $secondRecentLow, $doubleTopTolerance);

                // Check for Bullish RSI Divergence
                $hasBullishRsiDivergence = CommonHelpers::checkBullishRsiDivergence($recentLow, $secondRecentLow, $rsiDivergenceThreshold);

                $priceConfirmation = $data[$index]['close'] > $recentLow['low'] * 1.002;

                if ($isDoubleBottom && $hasBullishRsiDivergence && $priceConfirmation) {
                    $initialSetup = true;
                }
            }






            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

            // Define all steps with their conditions and scores
            $steps = [
                // Step 1 - Volume Confirmation
                [
                    'condition' => (

                        $initialSetup

                    ),
                    'candlesToCheck' => 10,
                ],




                // // Step 2 - Bullish Momentum
                // [
                //     'condition' => (


                //         $data[$index]['close'] >= $data[$index]['bb_middle']
                //         && $data[$index]['rsi6'] > 45
                //         && $bbAnalysis['is_expanding']


                //     ),
                //     'candlesToCheck' => 10
                // ],

                // Step 3 - Setup Formation
                // [
                //     'condition' => (


                //         $bbAnalysis['price_action']['is_near_lower_band']
                //         && $data[$index]['rsi6'] >= 25
                //         && $data[$index]['rsi6'] <= 45
                //         && $data[$index]['volume'] >= $data[$index]['volumeMA5']


                //     ),
                //     'candlesToCheck' => 20
                // ],

                // // Step 4 - Bullish Candle Check
                // [
                //     'condition' => (
                //         ($bbAnalysis['price_action']['is_near_lower_band'] || $bbAnalysis['price_action']['crossed_lower_band'])
                //         && $data[$index]['rsi6'] <= 20
                //         && $data[$index]['volume'] >= (1.5 * $data[$index]['volumeMA10'])
                //     ),
                //     'candlesToCheck' => 20
                // ],

                // // Final Step - Entry Execution
                // [
                //     'condition' => (

                //         $data[$index]['per'] > 0
                //         && $data[$index]['low'] < $data[$index]['bb_lower']

                //         // && $data[$index]['close'] >  $supportResistance['support']
                //         // && $bbAnalysis['bb_lower_percent_change'] > 0
                //         // && $bbAnalysis['bb_middle_percent_change'] > 0


                //     ),
                //     'candlesToCheck' => 10,
                // ],
                // [
                //     'condition' => (
                //         $data[$index]['volume'] >= (2 * $data[$index]['volumeMA5'])

                //     ),
                //     'candlesToCheck' => 10,
                // ]
            ];

            // Process steps sequentially
            foreach ($steps as $stepIndex => $step) {


                if (!$step['condition']) {
                    continue;
                }


                $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index);

                $isInitial = $stepIndex == 0;
                // Handle initial step (no existing trade required)
                if ($isInitial && !$confirmedTrade) {
                    self::insertConfirmBasicTradeEntry($symbol, 'TBD', $data, $index, 'LONG', $step['candlesToCheck']);
                    continue;
                }

                // Handle subsequent steps (existing trade with correct checkpoint required)
                $requiredCheckpoint = ($stepIndex == 0 ? null : ($stepIndex - 1));

                if ($confirmedTrade && $confirmedTrade->checkpoints == $requiredCheckpoint) {
                    self::updateConfirmTradeCheckpoint($symbol, 'TBD', $data, $index, 'LONG', $step['candlesToCheck']);

                    // Handle final step
                    $isFinal = $stepIndex === count($steps) - 1;

                    if ($isFinal) {
                        self::confirmOpening($symbol, 'TBD', $data, $index, 'LONG');

                        $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'LONG', $data, $index, '1h');

                        if (
                            $allowOnHigherTrend
                            && $data[$index]['obv'] > $data[$index - 1]['obv']
                            && $data[$index]['rsi6'] > $data[$index - 1]['rsi6']
                            // && $data[$index]['stoch_d'] > $data[$index - 1]['stoch_d']

                        )
                            return 'LONG';
                        else
                            return null;
                    }
                }
            }
        }
        // dd("Test");
        return null;
    }








    public static function checkConditionSetShortSR($symbol, $data, $index)
    {
        if (self::$shortEnabled) {
            $skippingReasons = [];

            if (!self::$isBaseReport) {
                $currentAccuracy = self::parseAccuracy(self::$progressionDetailsSHORTSR, $data[$index]['binance_timestamp'], 6);
                if ($currentAccuracy != -1) {
                    if ($currentAccuracy < 77) {
                        return null;
                    }
                }
            }

            $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
            $srAnalysis = $srAnalyzer->analyze();

            $entry = self::detectShortEntryWithSR($data, $index, $srAnalysis);

            if ($entry === 'SHORT')
                return $entry;
        }
        return null;
    }


    public static function checkConditionSetShortMACD($symbol, $data, $index)
    {
        if (self::$shortEnabled) {
            if (!self::$isBaseReport) {
                $currentAccuracy = self::parseAccuracy(self::$progressionDetailsSHORTMACD, $data[$index]['binance_timestamp'], 6);
                if ($currentAccuracy != -1) {
                    if ($currentAccuracy < 80) {
                        return null;
                    }
                }
            }


            // ======================================= MULTI STEP Setup for LONG entry =======================================


            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

            // Define all steps with their conditions and scores
            $steps = [
                // Step 1 - Volume Confirmation
                [
                    'condition' => (

                        $data[$index]['volume'] >= (1.2 * CommonHelpers::getSMAAtIndex($data, $index, 20, 'volume'))

                    ),
                    'candlesToCheck' => 10,
                ],

                // Step 2 - Bullish Momentum
                [
                    'condition' => (


                        $data[$index]['close'] <= $data[$index]['bb_middle']
                        && $data[$index]['rsi6'] < 65
                        && $bbAnalysis['is_expanding']


                    ),
                    'candlesToCheck' => 10
                ],

                // Step 3 - Setup Formation
                [
                    'condition' => (


                        $bbAnalysis['price_action']['is_near_upper_band']
                        && $data[$index]['rsi6'] >= 55
                        && $data[$index]['rsi6'] <= 75
                        && $data[$index]['volume'] >= $data[$index]['volumeMA5']


                    ),
                    'candlesToCheck' => 20
                ],

                // Step 4 - Bullish Candle Check
                [
                    'condition' => (
                        ($bbAnalysis['price_action']['is_near_upper_band'] || $bbAnalysis['price_action']['crossed_upper_band'])
                        && $data[$index]['rsi6'] >= 80
                        && $data[$index]['volume'] >= (1.5 * $data[$index]['volumeMA10'])
                    ),
                    'candlesToCheck' => 20
                ],

                // Final Step - Entry Execution
                [
                    'condition' => (

                        $data[$index]['per'] < 0
                        && $data[$index]['high'] > $data[$index]['bb_upper']

                        // && $data[$index]['close'] >  $supportResistance['support']
                        // && $bbAnalysis['bb_lower_percent_change'] > 0
                        // && $bbAnalysis['bb_middle_percent_change'] > 0


                    ),
                    'candlesToCheck' => 10,
                ],
                // [
                //     'condition' => (
                //         $data[$index]['volume'] >= (2 * $data[$index]['volumeMA5'])

                //     ),
                //     'candlesToCheck' => 10,
                // ]
            ];

            // Process steps sequentially
            foreach ($steps as $stepIndex => $step) {


                if (!$step['condition']) {
                    continue;
                }


                $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index);

                $isInitial = $stepIndex == 0;
                // Handle initial step (no existing trade required)
                if ($isInitial && !$confirmedTrade) {
                    self::insertConfirmBasicTradeEntry($symbol, 'TBD', $data, $index, 'SHORT', $step['candlesToCheck']);
                    continue;
                }

                // Handle subsequent steps (existing trade with correct checkpoint required)
                $requiredCheckpoint = ($stepIndex == 0 ? null : ($stepIndex - 1));

                if ($confirmedTrade && $confirmedTrade->checkpoints == $requiredCheckpoint) {
                    self::updateConfirmTradeCheckpoint($symbol, 'TBD', $data, $index, 'SHORT', $step['candlesToCheck']);

                    // Handle final step
                    $isFinal = $stepIndex === count($steps) - 1;

                    if ($isFinal) {
                        self::confirmOpening($symbol, 'TBD', $data, $index, 'SHORT');


                        $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'SHORT', $data, $index, '1h');

                        if (
                            $allowOnHigherTrend
                            && $data[$index]['obv'] < $data[$index - 1]['obv']
                            && $data[$index]['rsi6'] < $data[$index - 1]['rsi6']
                            // && $data[$index]['stoch_d'] > $data[$index - 1]['stoch_d']

                        )
                            return 'SHORT';
                        else
                            return null;
                    }
                }
            }
        }
        return null;
    }
}
