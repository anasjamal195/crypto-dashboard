<?php

namespace App\Services\InternalTrader\BaseReportWorkers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\OrderBookSnapshot;
use App\Services\HyperLiquidApiService;
use App\Services\OpeningConditionServiceLive;
use App\Services\SupportResistanceAnalyzer;
use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BaseReport15m
{


    // Essential Properties
    public static $delayMs = 10;
    public static $supportResistanceCandleSpan = 12;

    public static $interval = '15m';
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

    public static $activeExchange = 'binance';


    public static $dynamicTP = 0;
    public static $dynamicSL = 0;

    public static $dynamicTPSLgap = 0.2;

    public static $initialTpPercent = 0.5;
    public static $initialSlPercent = 1;



    public static $lows = [];
    public static $highs = [];




    // For Trendline strategy only
    public static $lastPivotLow = null;
    public static $lastPivotHigh = null;


    public static $tLineCoordHigh = null;
    public static $tLineCoordLow = null;

    public static $limit = 1000;


    public static $lowPivots = [];
    public static $highPivots = [];

    public static $failedOpenings = [];

    public static function generateCoinReport(
        $cmd = null,
        $formula = 'Default',
        $timestamp = null,
        $baseReportFormula = '',
        $baseReport = true
    ) {


        self::$formula = $formula;
        self::$backTestTimeUnix = $timestamp;
        self::$baseReportFormula = $baseReportFormula;
        self::$isBaseReport = $baseReport;




        $tradesTotal = [];

        $coins = [
            'BNBUSDT',
            'SOLUSDT',
            'ADAUSDT',
            'DOGEUSDT',
            'LTCUSDT',
            'LINKUSDT',
            'ATOMUSDT',
            'NEARUSDT',
            'RUNEUSDT',
            'UNIUSDT',
            'AAVEUSDT',
            'ALGOUSDT',
            'FILUSDT',
            'VETUSDT',
            'ICPUSDT',
            'SANDUSDT',
            'MANAUSDT',
            'AXSUSDT',
        ];


        self::$coinLimit = count($coins);
        self::addFormulaDetails();
        DB::table('confirmed_trades_safe_mode')->truncate();

        foreach ($coins as $index => $coin) {
            try {
                $symbol = $coin['symbol'];

                // Log::info("Test Request Params" . self::$interval);
                $data = self::$activeExchange === 'binance' ?
                    BinanceApiService::getCandleStickData($symbol, self::$interval, 250, null, 'FUTURE')
                    : HyperLiquidApiService::getCandleStickData($symbol, self::$interval, 250, null, 'FUTURE');

                $trades = self::processCandles($symbol, $data);

                // Insert trades into the database
                DB::table('coin_reports_safe_mode')
                    ->where('symbol', $symbol)
                    ->where('interval', self::$interval)
                    ->where('formula', self::$formula)
                    ->where('market', 'FUTURE')
                    ->delete();
                DB::table('coin_reports_safe_mode')->insert($trades);


                $tradesTotal[$symbol] = $trades;


                $perProgress = (($index + 1) / count($coins)) * 100;
                // system('clear');
                // $cmd->info('Processing: ' . round($perProgress) . ' %');
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

        return self::$formula;
    }

    public static function addFormulaDetails()
    {
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


        $classPath = app_path('Services/InternalTrader/BaseReportWorkers/BaseReport15m.php');

        // Output path
        $outputPath = storage_path('app/public/formula_bkp_service_' . self::$formula . '.txt');

        $contents = File::get($classPath);
        // File::put($outputPath, $contents);
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

        DB::table('formula_details')->updateOrInsert([
            'formula' => self::$formula,
        ], [
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

        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($data));

        $waitingCandles = 0;
        $openingIndex = 0;
        self::$safeModeTimestamp = null;

        $safeModeEnableTimestamps = [];
        $safeModeDisabledTimestamps = [];


        self::$tLineCoordHigh = null;
        self::$lastPivotLow = null;

        self::$tLineCoordLow = null;
        self::$lastPivotHigh = null;

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
            if ($index < 200) {
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

                    // Insert OPening Entry
                    // CommonHelpers::openInternalTrade($currentTrade);
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

                    self::$tLineCoordHigh = null;
                    self::$lastPivotLow = null;

                    self::$tLineCoordLow = null;
                    self::$lastPivotHigh = null;
                } else if ($index == (count($data) - 1)) {

                    // ForceFully Reset the trades if no closing found

                    $currentTrade['buyingPrice'] = $open_price;
                    $currentTrade['market'] = 'FUTURE';
                    $currentTrade['symbol'] = $symbol;
                    $currentTrade['interval'] = self::$interval;
                    $currentTrade['lowestPrice'] = $extremePrice;
                    $currentTrade['position'] = $tradeType;
                    $currentTrade['formula'] = self::$formula;



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

                    self::$tLineCoordHigh = null;
                    self::$lastPivotLow = null;

                    self::$tLineCoordLow = null;
                    self::$lastPivotHigh = null;
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

        return $trades;
    }




    // Function to check opening Conditions

    public static function handleOpeningConditions($symbol, $data, $index, $supportResistance, $orderBookSnapshot, $trades, &$safeModeEnableTimestamps, &$safeModeDisabledTimestamps, &$tagName)
    {

        $pivot = CommonHelpers::checkPivot($data, $index - 6, 6);

        if ($pivot === 'low_pivot') {
            self::$lowPivots[] = $index - 6;
        } else  
        if ($pivot === 'high_pivot') {
            self::$highPivots[] = $index - 6;
        }




        $lastPivotIndex = count(self::$lowPivots) - 1;

        if (
            $pivot === 'low_pivot'
            && count(self::$lowPivots) > 3
            && $data[self::$lowPivots[$lastPivotIndex]]['low'] > $data[self::$lowPivots[$lastPivotIndex - 1]]['low']
            && $data[self::$lowPivots[$lastPivotIndex - 1]]['low'] < $data[self::$lowPivots[$lastPivotIndex - 2]]['low']
            && $data[self::$lowPivots[$lastPivotIndex - 2]]['low'] < $data[self::$lowPivots[$lastPivotIndex - 3]]['low']
        ) {

            $firstPivotIndex = count(self::$lowPivots) - 3;
            $firstPivot = self::$lowPivots[$firstPivotIndex];
            $lastPivot = self::$lowPivots[$lastPivotIndex];
            $highPivots = [];
            for ($i = $firstPivot; $i <= $lastPivot; $i++) {
                $minorPivot = CommonHelpers::checkPivot($data, $i, 6);
                if ($minorPivot === 'high_pivot') {
                    $highPivots[] = $i;
                }
            }
            return 'LONG';
        }


        $lastPivotIndexHigh = count(self::$highPivots) - 1;

        if (

            $pivot === 'high_pivot'
            && count(self::$highPivots) > 3
            && $data[self::$highPivots[$lastPivotIndexHigh]]['high'] < $data[self::$highPivots[$lastPivotIndexHigh - 1]]['high']
            && $data[self::$highPivots[$lastPivotIndexHigh - 1]]['high'] > $data[self::$highPivots[$lastPivotIndexHigh - 2]]['high']
            && $data[self::$highPivots[$lastPivotIndexHigh - 2]]['high'] > $data[self::$highPivots[$lastPivotIndexHigh - 3]]['high']

        ) {


            $firstPivotIndex = count(self::$highPivots) - 3;
            $firstPivot = self::$highPivots[$firstPivotIndex];
            $lastPivot = self::$highPivots[$lastPivotIndexHigh];
            $lowPivots = [];
            for ($i = $firstPivot; $i <= $lastPivot; $i++) {
                $minorPivot = CommonHelpers::checkPivot($data, $i, 6);
                if ($minorPivot === 'low_pivot') {
                    $lowPivots[] = $i;
                }
            }

            if (count($lowPivots) >= 2) {

                $lastLowPivot = count($lowPivots) - 1;
                $firstLowPivot = count($lowPivots) - 2;
                if (
                    $data[$lowPivots[$firstLowPivot]]['low'] > $data[$lowPivots[$lastLowPivot]]['low']
                ) {
                    return null;
                }
            }


            if (count($lowPivots) == 0) {
                return null;
            }
            return 'SHORT';
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











        $trades = DB::table('coin_reports_safe_mode')
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
        $trades = DB::table('coin_reports_safe_mode')
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


        $rawData = DB::table('coin_reports_safe_mode')
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

        if ($tradeType === 'LONG') {

            // If TP is triggered
            if ($data[$index]['high'] >= self::$dynamicTP) {
                self::$dynamicTP = $data[$index]['high'] * (1 + self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $data[$index]['high'] * (1 - (self::$dynamicTPSLgap / 2) / 100);
            }
            // If Sl is trigggerd
            else if ($data[$index]['close'] < self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        } else {
            // If TP is triggered
            if ($data[$index]['low'] <= self::$dynamicTP) {
                self::$dynamicTP = $data[$index]['low'] * (1 - self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $data[$index]['low'] * (1 + (self::$dynamicTPSLgap / 2) / 100);
            }
            // If Sl is trigggerd
            else if ($data[$index]['close'] > self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
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



        $id =  DB::table('confirmed_trades_safe_mode')->insertGetId([
            'coin_name' => $symbol,
            'position' => $position,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => self::$candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => $bbDiffHighest,
            'bolling_last_squeezed_timestamp' => $data[$highestPointIndex]['binance_timestamp'],
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);
        return DB::table('confirmed_trades_safe_mode')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol, $position)
    {
        $lastEntry =  DB::table('confirmed_trades_safe_mode')->where('coin_name', $symbol)->where('position', $position)->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
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

        $lastEntry = DB::table('confirmed_trades_safe_mode')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->confirm_candle_timestamp, self::$interval);
        if ($indexDiff > $lastEntry->candles_to_check) {
            DB::table('confirmed_trades_safe_mode')->where('ict_id', $ictId)->update([
                'trade_confirmed' => 1,
                'update_time' => Carbon::now()->toDateTimeString(),
            ]);
            return null;
        }
        return $lastEntry;
    }




    public static function confirmOpening($symbol, $position, $data, $index)
    {

        DB::table('confirmed_trades_safe_mode')->where('coin_name', $symbol)->where('position', $position)->orderBy('update_time', 'DESC')->delete();
        return true;
    }
}
