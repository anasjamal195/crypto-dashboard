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
use App\Services\OpeningConditionServiceLive;
use App\Services\SupportResistanceAnalyzer;
use ArithmeticError;
use DivisionByZeroError;
use Exception;
use Illuminate\Support\Facades\Log;
use stdClass;
use Illuminate\Support\Facades\File;

class ReportService
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

    public static $dynamicTP = 0;
    public static $dynamicSL = 0;

    public static $dynamicTPSLgap = 0.2;

    public static $initialTpPercent = 0.6;
    public static $initialSlPercent = 2;



    public static $lows = [];
    public static $highs = [];




    // For Trendline strategy only
    public static $lastPivotLow = null;
    public static $lastPivotHigh = null;


    public static $tLineCoordHigh = null;
    public static $tLineCoordLow = null;

    public static $limit = 1000;


    public static $lowPivotsA = [];
    public static $highPivotsA = [];

    public static $lowPivotsB = [];
    public static $highPivotsB = [];

    public static $leftovers = [];

    public static $failedOpenings = [];
    public static $confirmedTradeIndex = null;
    public static $lpIndex = null;

    public static $waitingCandles = 0;

    public static $formulaType = null;


    public static $enteredZone = false;



    // Coins Stack


    public static $formulaACoins = [

        'BTCUSDT',
        'ETHUSDT',
        'SOLUSDT',
        'BNBUSDT',
        'ARBUSDT',
        'AVAXUSDT',
        'LINKUSDT',
        'TONUSDT',
        'TAOUSDT',
        // 'BNBUSDT',
        // 'SOLUSDT',
        // 'ADAUSDT',
        // 'DOGEUSDT',
        // 'LTCUSDT',
        // 'LINKUSDT',
        // 'ATOMUSDT',
        // 'NEARUSDT',
        // 'RUNEUSDT',
        // 'UNIUSDT',
        // 'AAVEUSDT',
        // 'ALGOUSDT',
        // 'FILUSDT',
        // 'VETUSDT',
        // 'ICPUSDT',
        // 'SANDUSDT',
        // 'MANAUSDT',
        // 'AXSUSDT',


        // // Major Altcoins
        // 'AVAXUSDT',
        // 'DOTUSDT',
        // 'TRXUSDT',
        // // 'SHIBUSDT',
        // 'XRPUSDT',

        // // DeFi/Layer 1 Tokens
        // 'FTMUSDT',
        // 'ONEUSDT',
        // 'EGLDUSDT',
        // 'ZILUSDT',
        // 'WAVESUSDT',

        // // Gaming/Metaverse
        // 'ENJUSDT',
        // 'CHZUSDT',
        // 'GALAUSDT',

        // // Established Altcoins
        // 'XLMUSDT',
        // 'EOSUSDT',
        // 'ETCUSDT',
        // 'BCHUSDT',

        // // Mid-caps with good patterns
        // 'CRVUSDT',
        // 'COMPUSDT',
        // 'MKRUSDT',
        // 'YFIUSDT'
    ];

    public static $formulaBCoins = [
        // 'BNBUSDT',      // Binance ecosystem - appeared in all tests
        // 'AVAXUSDT',     // Layer 1 - appeared in all tests  
        // 'VETUSDT',      // Supply chain - appeared in all tests
        // 'LTCUSDT',      // Established alt - appeared in 3 tests
        // 'SANDUSDT',     // Gaming/Metaverse - appeared in 3 tests
        // 'ADAUSDT',      // Major Layer 1 - appeared in 3 tests
        // 'MKRUSDT',      // DeFi governance - appeared in 3 tests
        // 'COMPUSDT',     // DeFi lending - appeared in 3 tests


        // // TIER 2: STRONG CANDIDATES (appeared in 2+ tests)
        // 'SOLUSDT',      // Major Layer 1
        // 'ATOMUSDT',     // Cosmos ecosystem
        // 'NEARUSDT',     // Layer 1 protocol
        // 'DOGEUSDT',     // High volume meme coin
        // 'AAVEUSDT',     // DeFi lending
        // 'FILUSDT',      // Decentralized storage
        // 'ETCUSDT',      // Ethereum Classic
        // 'CHZUSDT',      // Sports tokens
        // 'ICPUSDT',      // Internet Computer
        // 'EGLDUSDT',     // MultiversX
        // 'XRPUSDT',      // Payment token
        // 'TRXUSDT',      // Established blockchain
        // 'UNIUSDT',      // Leading DEX
        // 'BCHUSDT',      // Bitcoin fork
        // 'CRVUSDT',      // DeFi yield farming
        // 'ALGOUSDT',     // Pure proof-of-stake
        // 'MANAUSDT',     // Metaverse
        // 'GALAUSDT',     // Gaming


        // // TIER 3: ADDITIONAL HIGH-POTENTIAL COINS
        // // Based on similar characteristics

        // // Layer 1 & Infrastructure (similar to AVAX, SOL, ADA patterns)
        // 'DOTUSDT',      // Polkadot - Multi-chain protocol
        // 'MATICUSDT',    // Polygon - Ethereum scaling
        // 'FTMUSDT',      // Fantom - High-speed blockchain
        // 'HBARUSDT',     // Hedera - Enterprise blockchain
        // 'FLOWUSDT',     // Flow - NFT-focused blockchain
        // 'APTUSDT',      // Aptos - High-performance L1
        // 'SUIUSDT',      // Sui - Next-gen L1
        // 'SEIUSDT',      // Sei - Trading-focused L1

        // // DeFi Tokens (similar to AAVE, UNI, CRV patterns)
        // 'LINKUSDT',     // Chainlink - Oracle network
        // 'SUSHIUSDT',    // SushiSwap - DEX
        // 'CAKEUSDT',     // PancakeSwap - BSC DEX
        // '1INCHUSDT',    // 1inch - DEX aggregator
        // 'SNXUSDT',      // Synthetix - Synthetic assets
        // 'GMXUSDT',      // GMX - Perpetual trading
        // 'RDNTUSDT',     // Radiant - Cross-chain lending
        // 'PEPEUSDT',     // High volume meme token

        // // // Gaming/Metaverse (similar to SAND, MANA, GALA patterns)
        // 'AXSUSDT',      // Axie Infinity - P2E gaming
        // 'ENJUSDT',      // Enjin - Gaming platform
        // 'IMXUSDT',      // Immutable X - Gaming L2
        // 'BEAMUSDT',     // Beam - Gaming blockchain
        // 'RONINUSDT',    // Ronin - Gaming sidechain
        // 'MAGICUSDT'    // Magic - Gaming ecosystem
    ];



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






        $coins = array_values(array_unique(array_merge(self::$formulaACoins, self::$formulaBCoins)));



        $tradesTotal = [];

        // $coins = BinanceApiService::fetchTopUSDTPairsByVolume(100);
        // dd($coins);

        self::$coinLimit = count($coins);
        // Clear Console
        system('clear');
        $cmd->info('Processing: 0 %');


        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();

        foreach ($coins as $index => $coin) {

            try {


                $symbol = $coin;


                $data = BinanceApiService::getCandleStickDataExtended($symbol, self::$interval, self::$limit, self::$backTestTimeUnix, 'FUTURE');

                // dd($data[count($data) - 2]);


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

        // dd("Completed");

        // dd("Completed", self::$leftovers);
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
            self::$backTestTimeUnix = time() * 1000 - (CommonHelpers::$binanceIntervals[self::$interval] * 60 * 1000 * self::$limit);
        }


        if (self::$backTestTimeUnix) {
            $diffInMins = CommonHelpers::$binanceIntervals[self::$interval];

            $startUnix = self::$backTestTimeUnix;
            $endUnix = self::$backTestTimeUnix + ($diffInMins * 60 * 1000 * self::$limit);

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

        $data = array_map(function ($candle) {
            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            return $candle;
        }, array_merge($data));

        self::$waitingCandles = 0;
        $openingIndex = 0;
        self::$safeModeTimestamp = null;

        $safeModeEnableTimestamps = [];
        $safeModeDisabledTimestamps = [];


        self::$tLineCoordHigh = null;
        self::$lastPivotLow = null;

        self::$tLineCoordLow = null;
        self::$lastPivotHigh = null;

        self::$lowPivotsA = [];
        self::$highPivotsA = [];

        self::$lowPivotsB = [];
        self::$highPivotsB = [];

        self::$enteredZone = false;
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



            $pivot_depth = 6;

            $pivot = CommonHelpers::checkPivot($data, $index - $pivot_depth, $pivot_depth);


            if ($pivot === 'high_pivot') {
                self::$highPivotsA[] = $index - $pivot_depth;
            }
            if ($pivot === 'low_pivot') {
                self::$lowPivotsA[] = $index - $pivot_depth;
            }





            // Highs and Lows Calculation

            $pivot_depth = 2;

            $pivot = CommonHelpers::checkPivot($data, $index - $pivot_depth, $pivot_depth);


            if ($pivot === 'high_pivot') {
                self::$highPivotsB[] = $index - $pivot_depth;
            }
            if ($pivot === 'low_pivot') {
                self::$lowPivotsB[] = $index - $pivot_depth;
            }












            // 20 mins weight after each trade

            if (self::$waitingCandles) {
                self::$waitingCandles--;
                continue;
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
                    $candle['formulaType'] = self::$formulaType;
                    if (self::$confirmedTradeIndex)
                        $candle['confirmTradeTimestamp'] = $data[self::$confirmedTradeIndex]['timestamp_pst'];
                    if (self::$lpIndex)
                        $candle['lpIndex'] = $data[self::$lpIndex]['timestamp_pst'];



                    $detector = new OrderBlockDetector();
                    $orderBlocks = $detector->getRecentOrderBlocks($data, $index, 5);




                    if (count($orderBlocks['bear'])) {
                        $latestZone = $orderBlocks['bear'][0];
                        $candle['latestBearOb'] = $latestZone;
                    }
                    if (count($orderBlocks['bull'])) {
                        $latestZone = $orderBlocks['bull'][0];
                        $candle['latestBullOb'] = $latestZone;
                    }




                    // if (count(self::$lowPivots) >= 3)
                    //     $candle['lowPivots'] = [
                    //         $data[self::$lowPivots[count(self::$lowPivots) - 3]]['timestamp_pst'],
                    //         $data[self::$lowPivots[count(self::$lowPivots) - 2]]['timestamp_pst'],
                    //         $data[self::$lowPivots[count(self::$lowPivots) - 1]]['timestamp_pst'],
                    //     ];
                    // if (count(self::$highPivots) >= 3)
                    //     $candle['highPivots'] = [
                    //         $data[self::$highPivots[count(self::$highPivots) - 3]]['timestamp_pst'],
                    //         $data[self::$highPivots[count(self::$highPivots) - 2]]['timestamp_pst'],
                    //         $data[self::$highPivots[count(self::$highPivots) - 1]]['timestamp_pst'],
                    //     ];



                    $open_price = $candle['close'];





                    // Get Trend Details

                    if ($tradeType === 'LONG') {

                        $sl = $index;

                        if (self::$formulaType === 'A') {

                            $sl = self::$lowPivotsA[count(self::$lowPivotsA) - 2];

                            $loopIndex = count(self::$lowPivotsA) - 1;
                            while ($loopIndex >= 0 && $data[self::$lowPivotsA[$loopIndex]]['low'] >= $data[$index]['close']) {
                                $sl = self::$lowPivotsA[$loopIndex];
                                $loopIndex--;
                            }
                        } else if (self::$formulaType === 'B') {
                            $sl = self::$lowPivotsB[count(self::$lowPivotsB) - 2];

                            $loopIndex = count(self::$lowPivotsB) - 1;
                            while ($loopIndex >= 0 && $data[self::$lowPivotsB[$loopIndex]]['low'] >= $data[$index]['close']) {
                                $sl = self::$lowPivotsB[$loopIndex];
                                $loopIndex--;
                            }
                        }


                        if ($data[$sl]['low'] >= $data[$index]['close']) {
                            // self::$dynamicSL = $data[$index]['close'] * (1 - self::$initialSlPercent / 100);
                        } else {
                            $slPercentage = CommonHelpers::getPercentDiff($data[$index]['close'], $data[$sl]['low']);
                            // self::$dynamicSL = $data[$sl]['low'] * (1 - 0.7 / 100);

                            // if ($slPercentage >= 3) {
                            //     $extremePrice = 0;
                            //     $currentTrade = [];
                            //     $open_price = 0;
                            //     $tradeType = null;
                            //     self::$waitingCandles = 4;
                            //     $openingIndex = 0;

                            //     self::$dynamicTP = 0;
                            //     self::$dynamicSL = 0;
                            //     self::$candlesToCheck = 1000;

                            //     self::$tLineCoordHigh = null;
                            //     self::$lastPivotLow = null;

                            //     self::$tLineCoordLow = null;
                            //     self::$lastPivotHigh = null;
                            //     self::$confirmedTradeIndex = null;
                            //     self::$lpIndex = null;
                            //     continue;
                            // }
                        }


                        // self::$dynamicTP = $data[$index]['close'] * (1 + self::$initialTpPercent / 100);

                        $candle['slPer'] = CommonHelpers::getPercentDiff($open_price, self::$dynamicSL);
                        $candle['tpSlBuffer'] = self::$dynamicTPSLgap;
                    }





                    $candle['trendDetails'] = json_encode(CommonHelpers::detectTrend($data, $index, 50, 50));
                    $currentTrade['buyingCandle'] = json_encode($candle);
                    $currentTrade['previousCandle'] = json_encode($data[$index - 1]);
                    $currentTrade['tagName'] = $tagName;
                    $currentTrade['openingTimestamp'] = $data[$index]['binance_timestamp'];


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

                    error_log("$tradeType Entry " . self::$formulaType . " for {$symbol}: " . $currentTrade['profit']);


                    // Resetting params
                    $extremePrice = 0;
                    $trades[] = $currentTrade;
                    $currentTrade = [];
                    $open_price = 0;
                    $tradeType = null;
                    self::$waitingCandles = 4;
                    $openingIndex = 0;

                    self::$dynamicTP = 0;
                    self::$dynamicSL = 0;
                    self::$candlesToCheck = 1000;

                    self::$tLineCoordHigh = null;
                    self::$lastPivotLow = null;

                    self::$tLineCoordLow = null;
                    self::$lastPivotHigh = null;
                    self::$confirmedTradeIndex = null;
                    self::$lpIndex = null;
                    self::$formulaType = null;
                }
            }
        }


        if ($index == (count($data) - 1) && !empty($currentTrade)) {

            self::$leftovers[] = $symbol;
        }

        self::confirmOpening($symbol, 'TBD', $data, $index, 'TBD');

        self::$lows = [];
        self::$highs = [];

        // self::logSafeModeEntry(self::$formula, $symbol, $safeModeEnableTimestamps, $safeModeDisabledTimestamps);
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



        // ############### LONG CONDITIONS ####################






        $detector = new OrderBlockDetector();
        $orderBlocks = $detector->getRecentOrderBlocks($data, $index, 5);




        if (count($orderBlocks['bear'])) {
            $latestZone = $orderBlocks['bear'][0];
            if (!self::$enteredZone) {
                // If entered zone
                if (
                    $data[$index]['close'] <= $latestZone['top']
                    && $data[$index]['close'] >= $latestZone['bottom']
                ) {
                    self::$enteredZone = true;
                }
            } else {
                if (
                    $data[$index]['close'] > $latestZone['top']
                    && $data[$index]['open'] < $latestZone['top']
                    && $data[$index]['close'] > $data[$index]['ma99']
                ) {
                    self::$enteredZone = false;
                    $stopLoss = $latestZone['bottom'];
                    $minLow = min($data[$index]['low'], $data[$index - 1]['low'], $data[$index - 2]['low']);
                    if (
                        $minLow <  $latestZone['bottom']
                    ) {
                        $stopLoss = $minLow;
                    }

                    self::$dynamicSL = $stopLoss;
                    self::$dynamicTP = $data[$index]['close'] + (($data[$index]['close'] - $stopLoss) * 1.5);
                    return 'LONG';
                } else  if (
                    $data[$index]['close'] < $latestZone['bottom']
                    && $data[$index]['open'] > $latestZone['bottom']
                ) {
                    self::$enteredZone = false;
                }
            }
        }



        // if (in_array($symbol, self::$formulaACoins)) {
        //     $openingA = self::checkOpeningConditionA($symbol, $data, $index);
        //     if ($openingA) {
        //         $tagName = 'Formula-A';
        //         self::$formulaType = 'A';
        //         return $openingA;
        //     }
        // }


        // if (in_array($symbol, self::$formulaBCoins)) {
        //     $openingB = self::checkOpeningConditionB($symbol, $data, $index);
        //     if ($openingB) {
        //         $tagName = 'Formula-B';
        //         self::$formulaType = 'B';
        //         return $openingB;
        //     }
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
                    SUM(profit) as profit,
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
                    'profit' => 0,
                    'accuracy' => 0,
                    'high_accuracy_symbols' => [],
                ];
            }

            $grouped[$timestamp]['total_profit'] += $row->profitable_trades;
            $grouped[$timestamp]['total_loss'] += $row->loss_trades;
            $grouped[$timestamp]['profit'] += $row->profit;

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
    public static function parseProfit($grouped, $endTime, $hours = null)
    {

        $filterHoursStartTime = $endTime - ($hours * 60 * 60 * 1000);


        if (!$hours) {
            $filterHoursStartTime = 0;
        }

        $totalProfits = 0;
        $totalLosses = 0;
        $netProfit = 0;

        foreach ($grouped as $timestamp => $data) {
            if ($timestamp <= $endTime && $timestamp >= $filterHoursStartTime) {
                $totalLosses += $data['total_loss'];
                $totalProfits += $data['total_profit'];
                $netProfit += $data['profit'];
            }
        }




        return $netProfit;
    }

    public static function handleClosingConditions($symbol, $data, $index, $tradeType, $openingIndex, $open_price)
    {
        $candle = $data[$index];
        $closingPrice = 0;


        if ($tradeType === 'LONG') {

            // // If TP is triggered
            if ($data[$index]['high'] >= self::$dynamicTP) {
                self::$dynamicTP = $data[$index]['high'] * (1 + self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $data[$index]['high'] * (1 - (self::$dynamicTPSLgap / 2) / 100);
            }

            // // If Sl is trigggerd
            else if ($data[$index]['low'] < self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }

            // else if (

            //     $data[$index]['histogram'] < $data[$index - 1]['histogram']
            //     && $data[$index]['close'] < $data[$index]['ma99']
            //     && $data[$index]['per'] < 0
            //     // && CommonHelpers::getPercentDiff($data[$openingIndex]['close'], self::$dynamicSL, true) <= -2
            //     && CommonHelpers::getPercentDiff($data[$openingIndex]['close'], $data[$index]['close'], true) <= -1
            // ) {
            //     $closingPrice = $data[$index]['close'];
            // }
        } else {
            // If TP is triggered
            if ($data[$index]['close'] <= self::$dynamicTP) {
                self::$dynamicTP = $data[$index]['close'] * (1 - self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $data[$index]['close'] * (1 + (self::$dynamicTPSLgap / 2) / 100);
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
            $srAnalysis = null;
            try {
                $srAnalyzer = new SupportResistanceAnalyzer($data, $index, 300);
                $srAnalysis = $srAnalyzer->analyze();
            } catch (DivisionByZeroError $e) {
                // Handle division by zero specifically
                error_log("Division by zero in SR analysis: " . $e->getMessage());
                $srAnalysis = null;
            } catch (ArithmeticError $e) {
                // Handle other arithmetic errors (includes division by zero in some cases)
                error_log("Arithmetic error in SR analysis: " . $e->getMessage());
                $srAnalysis = null;
            } catch (Exception $e) {
                // Handle any other exceptions
                error_log("General error in SR analysis: " . $e->getMessage());
                $srAnalysis = null;
            }


            if ($srAnalysis['market_structure']['nearest_support'] && $srAnalysis['market_structure']['nearest_support']['classification'] === 'major') {

                $supportPrice = $srAnalysis['market_structure']['nearest_support']['price'];



                // $lookBack = 100;

                // for ($i = $index - 5; $i >= ($index - $lookBack); $i--) {

                //     $pivot = CommonHelpers::checkPivot($data, $i, 5);

                //     if ($pivot === 'low_pivot') {
                //         self::$lastPivotLow = [
                //             'index' => $i,
                //         ];

                //         break;
                //     }
                // }


                // if (self::$lastPivotLow) {
                //     if (
                //         $data[self::$lastPivotLow['index']]['low'] <= $supportPrice
                //         && $data[self::$lastPivotLow['index'] - 1]['low'] > $supportPrice
                //     ) {
                //         $initialSetup = true;
                //     }
                // }

                $candleInteraction  = CommonHelpers::checkCandleOverlap($data, $index, $supportPrice, 'support');


                if ($candleInteraction['overlap_analysis']['body_position'] === 'partial_from_above') {
                    // dd($data[$index],$candleInteraction);
                    self::$lastPivotLow = [
                        'index' => $index,
                        'price' => $supportPrice,
                    ];
                    $initialSetup = true;
                }
            }




            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

            $secondInteraction = false;


            if (self::$lastPivotLow) {
                $supportPrice = self::$lastPivotLow['price'];

                $candleInteraction  = CommonHelpers::checkCandleOverlap($data, $index, $supportPrice, 'support');

                $secondInteraction = (
                    $candleInteraction['overlap_analysis']['body_position'] === 'partial_from_above'
                    && ($index - self::$lastPivotLow['index']) >= 3
                );
            }





            // Define all steps with their conditions and scores
            $steps = [
                // Step 1 - Volume Confirmation
                [
                    'condition' => (

                        $initialSetup

                    ),
                    'candlesToCheck' => 100,
                ],


                [
                    'condition' => (

                        $secondInteraction

                    ),
                    'candlesToCheck' => 20,
                ],



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

                        return 'LONG';
                    }
                }
            }
        }
        // dd("Test");
        return null;
    }

    public static function checkOpeningConditionA($symbol, $data, $index)
    {

        $lastPivotIndex = count(self::$lowPivotsA) - 1;
        $checkPreviousCollision = true;
        for ($i = $lastPivotIndex; $i < $index - 2; $i++) {
            if (
                count(self::$lowPivotsA) > 3
                && $data[$i]['low'] <=  ($data[self::$lowPivotsA[$lastPivotIndex]]['low'] * (1 - 0.2 / 100))
                && $data[$i]['close'] >= ($data[self::$lowPivotsA[$lastPivotIndex]]['low'] * (1 + 0 / 100))

            ) {
                $checkPreviousCollision = false;
                break;
            }
        }
        $regularMacdRed = true;

        $loopIndex  = $index - 1;
        while ($loopIndex >= 3 && $data[$loopIndex]['histogram'] < 0) {
            if (
                $data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'] // dark candle
                && $data[$loopIndex - 1]['histogram'] > $data[$loopIndex - 2]['histogram'] // light candle
            ) {
                $regularMacdRed = false;
                break;
            }
            $loopIndex--;
        }

        if (
            count(self::$lowPivotsA) > 3
            && $data[$index]['low'] <=  ($data[self::$lowPivotsA[$lastPivotIndex]]['low'] * (1 - 0.1 / 100))
            && $data[$index]['close'] > ($data[self::$lowPivotsA[$lastPivotIndex]]['low'] * (1 + 0.05 / 100))
            && $checkPreviousCollision
            && $regularMacdRed

        ) {
            return 'LONG';
        }

        return null;
    }


    public static function checkOpeningConditionB($symbol, $data, $index)
    {

        $numberOfTouchLow = 0;
        $currentLow = $data[$index]['low'];
        foreach (self::$lowPivotsB as $lpIndex) {
            if (
                $data[$lpIndex]['low'] <= $currentLow * (1 + 0.01 / 100)
                && $data[$lpIndex]['low'] >= $currentLow * (1 - 0.01 / 100)
                && $lpIndex < $index
            ) {
                $numberOfTouchLow++;
            }
        }
        if (
            count(self::$lowPivotsB) > 3
            && $data[$index]['low']  > $data[$index]['ema200']
            && $data[$index]['bb_middle'] >= $data[$index]['bb_middle']
            && $numberOfTouchLow >= 2

        ) {
            return 'LONG';
        }
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




    public static function checkPreviousTriggerBullish($data, $index, $interval, $confirmedTrade)
    {
        $ctIndex = self::getIndexDiffFromTimestamps($confirmedTrade->confirm_candle_timestamp, $data[$index]['binance_timestamp'], self::$interval, true);
        $ctIndex = $index - $ctIndex;

        $verifiedIndex = $index;

        for ($i = $ctIndex; $i <= $index; $i++) {


            if ($data[$i]['per'] > 0.1) {
                $verifiedIndex = $i;
                break;
            }
        }

        return [
            'verifiedIndex' => $verifiedIndex,
            'currentIndex' => $index,
            'verifiedTimestamp' => $data[$verifiedIndex]['timestampReadable'],
            'verifiedTimestampUnix' => $data[$verifiedIndex]['binance_timestamp'],
            'currentTimestamp' => $data[$index]['timestampReadable'],
            'currentTimestampUnix' => $data[$index]['binance_timestamp'],
            'percentGain' => CommonHelpers::getPercentDiff($data[$verifiedIndex]['close'], $data[$index]['close'], true),
            'numberOfCandlesPast' => $index - $verifiedIndex,
            'diffInMins' => ($data[$index]['binance_timestamp'] - $data[$verifiedIndex]['binance_timestamp']) / (1000 * 60),
        ];
    }
}







class OrderBlockDetector
{
    private $mslen = 5; // Market structure length (equivalent to mslen in Pine Script)
    private $atrPeriod = 200; // ATR calculation period
    private $obmode = "Length"; // "Length" or "Full"
    private $len = 5; // Length parameter for order block construction

    public function __construct($mslen = 5, $atrPeriod = 200, $obmode = "Length", $len = 5)
    {
        $this->mslen = $mslen;
        $this->atrPeriod = $atrPeriod;
        $this->obmode = $obmode;
        $this->len = $len;
    }

    /**
     * Get recent order blocks up to the specified candle index
     * 
     * @param array $data - Array of candlestick data
     * @param int $index - Current index to analyze up to
     * @param int $maxOrderBlocks - Maximum number of order blocks to return (default: 5)
     * @return array - Array of order blocks with bull/bear classification
     */
    public function getRecentOrderBlocks($data, $index, $maxOrderBlocks = 5)
    {
        if ($index < $this->mslen * 2 + $this->atrPeriod) {
            return ['bull' => [], 'bear' => []]; // Not enough data
        }

        // Initialize market structure state
        $ms = $this->initializeMarketStructure($data, $index);

        // Calculate ATR for order block positioning
        $atr = $this->calculateATR($data, $index);

        // Find order blocks
        $bullOrderBlocks = [];
        $bearOrderBlocks = [];

        // Track market structure changes and identify order block formation points
        $structureChanges = $this->findStructureChanges($data, $index);

        foreach ($structureChanges as $change) {
            if ($change['type'] === 'choch_bull_to_bear') {
                // Bullish order block formed
                $ob = $this->createBullOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bullOrderBlocks[] = $ob;
                }
            } elseif ($change['type'] === 'choch_bear_to_bull') {
                // Bearish order block formed  
                $ob = $this->createBearOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bearOrderBlocks[] = $ob;
                }
            }
        }

        // Sort by recency and limit results
        usort($bullOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });

        usort($bearOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });


        $bullZone = [];
        $bearZone = [];
        foreach (array_slice($bullOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bullZone[] = $zone;
            }
        }

        foreach (array_slice($bearOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bearZone[] = $zone;
            }
        }

        return [
            'bull' => $bullZone,
            'bear' => $bearZone
        ];
    }

    private function initializeMarketStructure($data, $index)
    {
        return [
            'trend' => 0, // 1 for uptrend, -1 for downtrend, 0 for initial
            'start' => 0, // Market structure state
            'choch' => null, // Change of character level
            'bos' => null, // Break of structure level
            'main' => null, // Current main level being tracked
            'loc' => 0, // Location of last structure change
            'temp' => 0, // Temporary location tracker
        ];
    }

    private function calculateATR($data, $index, $period = null)
    {
        if ($period === null) {
            $period = min($this->atrPeriod, $index);
        }

        $trValues = [];
        $startIdx = max(1, $index - $period + 1);

        for ($i = $startIdx; $i <= $index; $i++) {
            $high = $data[$i]['high'];
            $low = $data[$i]['low'];
            $prevClose = $data[$i - 1]['close'];

            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            $trValues[] = $tr;
        }

        return array_sum($trValues) / count($trValues);
    }

    private function findStructureChanges($data, $index)
    {
        $changes = [];
        $ms = $this->initializeMarketStructure($data, $index);

        // Find pivot highs and lows
        $pivots = $this->findPivots($data, $index);

        // Analyze structure changes
        $trend = 0; // 0 = initial, 1 = up, -1 = down
        $lastHigh = null;
        $lastLow = null;

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            $current = $data[$i];

            // Check for pivot highs and lows
            if ($this->isPivotHigh($data, $i)) {
                if ($lastHigh !== null && $current['high'] < $lastHigh) {
                    // Lower high - potential trend change to bearish
                    if ($trend === 1) {
                        $changes[] = [
                            'type' => 'choch_bull_to_bear',
                            'index' => $i,
                            'price' => $current['high'],
                            'timestamp' => $current['timestamp'],
                            'prev_high' => $lastHigh
                        ];
                        $trend = -1;
                    }
                } elseif ($lastHigh !== null && $current['high'] > $lastHigh) {
                    // Higher high - continuation or new bullish trend
                    if ($trend !== 1) {
                        $trend = 1;
                    }
                }
                $lastHigh = $current['high'];
            }

            if ($this->isPivotLow($data, $i)) {
                if ($lastLow !== null && $current['low'] > $lastLow) {
                    // Higher low - potential trend change to bullish
                    if ($trend === -1) {
                        $changes[] = [
                            'type' => 'choch_bear_to_bull',
                            'index' => $i,
                            'price' => $current['low'],
                            'timestamp' => $current['timestamp'],
                            'prev_low' => $lastLow
                        ];
                        $trend = 1;
                    }
                } elseif ($lastLow !== null && $current['low'] < $lastLow) {
                    // Lower low - continuation or new bearish trend
                    if ($trend !== -1) {
                        $trend = -1;
                    }
                }
                $lastLow = $current['low'];
            }
        }

        return $changes;
    }

    private function findPivots($data, $index)
    {
        $pivots = ['highs' => [], 'lows' => []];

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            if ($this->isPivotHigh($data, $i)) {
                $pivots['highs'][] = ['index' => $i, 'price' => $data[$i]['high']];
            }
            if ($this->isPivotLow($data, $i)) {
                $pivots['lows'][] = ['index' => $i, 'price' => $data[$i]['low']];
            }
        }

        return $pivots;
    }

    private function isPivotHigh($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentHigh = $data[$index]['high'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        // Check right side  
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        return true;
    }

    private function isPivotLow($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentLow = $data[$index]['low'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        // Check right side
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        return true;
    }

    private function createBullOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the highest point before the structure change
        $highestIdx = $this->findHighest($data, max(0, $idx - 20), $idx);
        if ($highestIdx === null) return null;

        $candle = $data[$highestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust bottom based on mode
        if ($this->obmode === "Length") {
            $adjustedBottom = $candle['low'] + ($atr / (5 / $this->len));
            $bottom = min($adjustedBottom, $candle['high']) > $candle['low'] ? $candle['low'] : $adjustedBottom;
        }

        return [
            'type' => 'bull',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $highestIdx,
            'timestamp' => $candle['binance_timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function createBearOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the lowest point before the structure change  
        $lowestIdx = $this->findLowest($data, max(0, $idx - 20), $idx);
        if ($lowestIdx === null) return null;

        $candle = $data[$lowestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust top based on mode
        if ($this->obmode === "Length") {
            $adjustedTop = $candle['high'] - ($atr / (5 / $this->len));
            $top = max($adjustedTop, $candle['low']) < $candle['high'] ? $candle['high'] : $adjustedTop;
        }

        return [
            'type' => 'bear',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $lowestIdx,
            'timestamp' => $candle['binance_timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function findHighest($data, $startIdx, $endIdx)
    {
        $highest = null;
        $highestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($highest === null || $data[$i]['high'] > $highest) {
                $highest = $data[$i]['high'];
                $highestIdx = $i;
            }
        }

        return $highestIdx;
    }

    private function findLowest($data, $startIdx, $endIdx)
    {
        $lowest = null;
        $lowestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($lowest === null || $data[$i]['low'] < $lowest) {
                $lowest = $data[$i]['low'];
                $lowestIdx = $i;
            }
        }

        return $lowestIdx;
    }
}
