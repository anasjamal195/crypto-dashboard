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
    public static $interval = '15m';
    public static $longEnabled = true;
    public static $shortEnabled = true;
    public static $earlyClosingEnabled = true;
    // Trend Analysis
    public static $trendReferenceSymbol = 'HBARUSDT';
    public static $trendReferenceInterval = '1h';
    // Coin Selection Filters
    public static $coinLimit = 0;
    public static $shuffleCoins = false;
    public static $filterOnCoinType = true;
    public static $coinTypeMetaverse = true;
    public static $coinTypeAlt = true;
    public static $coinTypeMeme = false;
    public static $coinTypeDefi = true;
    public static $coinTypeNft = false;
    public static $coinTypeWeb3 = false;
    public static $backTestTimeUnix;
    public static $formula;
    public static $leftovers = [];
    public static $waitingCandles = 0;
    public static $formulaType = null;
    public static $openingIndex = 0;
    public static $extremePrice = null;
    public static $trades = null;
    public static $currentTrade = null;
    public static $open_price = null;
    public static $tradeType = null;


    // Level Details 
    public static $sLevels = [];
    public static $rLevels = [];
    public static $currentFVG = null;
    public static $currentZoneStatus = null;



    // Basic TP SL Settings
    public static $dynamicTP = 0;
    public static $dynamicSL = 0;
    public static $dynamicTPSLgap = 0.2;
    public static $initialTpPercent = 1;
    public static $initialSlPercent = 1;


    // Limits for candle data 
    public static $limit = 1000;
    public static $initialWaitingCandles = 200;



    // Coins Config
    public static $formulaACoins = [
        'BTCUSDT',
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
        // 'AXSUSDT'
    ];

    public static $formulaBCoins = [
        'BTCUSDT',
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
        // 'AXSUSDT'
    ];



    public static function generateCoinReport(
        $cmd = null,
        $formula = 'Default',
        $timestamp = null,
    ) {




        $coins = array_values(array_unique(array_merge(self::$formulaACoins, self::$formulaBCoins)));

        self::$formula = $formula;
        self::$backTestTimeUnix = $timestamp;
        self::$coinLimit = count($coins);



        system('clear');
        $cmd->info('Processing: 0 %');
        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();

        foreach ($coins as $index => $coin) {

            try {
                $symbol = $coin;
                $data = BinanceApiService::getCandleStickDataExtended($symbol, self::$interval, self::$limit, self::$backTestTimeUnix, 'FUTURE');
                self::$trades = self::processCandles($symbol, $data);
                // Insert trades into the database
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();
                DB::table('coin_reports')->insert(self::$trades);
                $perProgress = (($index + 1) / count($coins)) * 100;
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

    protected static function processCandles($symbol, $data)
    {


        self::$extremePrice = 0;
        self::$waitingCandles = 0;
        self::$openingIndex = 0;
        self::$sLevels = [];
        self::$rLevels = [];
        self::$open_price = 0;
        self::$tradeType = null;
        self::$currentTrade = [];
        self::$trades = [];


        $data4hRaw = BinanceApiService::getCandleStickDataPast($symbol, '4h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
        $data1hRaw = BinanceApiService::getCandleStickDataPast($symbol, '1h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');


        foreach ($data as $index => $candle) {


            // Skip Adjustment Candles and Volume Adjustment
            if ($index < self::$initialWaitingCandles) {
                continue;
            }

            self::setCurrentFVG($data, $data1hRaw, $index);
            self::setSRLevels($data, $data4hRaw, $index);

            // 20 mins weight after each trade

            if (self::$waitingCandles) {
                self::$waitingCandles--;
                continue;
            }


            if (self::$open_price == 0) {
                self::$tradeType = self::handleOpeningConditions($symbol, $data, $index);
                if (
                    self::$tradeType
                ) {
                    $candle['formulaType'] = self::$formulaType;
                    $candle['dynamicTP'] = self::$dynamicTP;
                    $candle['dynamicSL'] = self::$dynamicSL;


                    if (self::$currentZoneStatus) {
                        if (self::$currentZoneStatus['zoneType'] === 'support')
                            $candle['latestBearOb'] = self::$currentZoneStatus['currentZone'];
                        if (self::$currentZoneStatus['zoneType'] === 'resistance')
                            $candle['latestBullOb'] = self::$currentZoneStatus['currentZone'];
                    }

                    self::$currentTrade['buyingCandle'] = json_encode($candle);
                    self::$currentTrade['previousCandle'] = json_encode($data[$index - 1]);
                    self::$currentTrade['openingTimestamp'] = $data[$index]['binance_timestamp'];

                    self::$open_price = $candle['close'];
                    self::$extremePrice = self::$open_price;
                    self::$openingIndex = $index;
                }
            } else {
                $closingPrice =  self::handleClosingConditions($symbol, $data, $index);

                // Closing Sequence
                if (self::$tradeType === 'SHORT') {
                    self::$extremePrice =  max(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'high'));
                }
                if (self::$tradeType === 'LONG') {
                    self::$extremePrice =  min(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'low'));
                }


                if ($closingPrice) {
                    $profit = self::$tradeType === 'LONG' ? round(($closingPrice - self::$open_price) / self::$open_price * 100, 2) : round((self::$open_price - $closingPrice) / self::$open_price * 100, 2);
                    self::$currentTrade['sellingCandle'] = json_encode($candle);
                    self::$currentTrade['buyingPrice'] = self::$open_price;
                    self::$currentTrade['market'] = 'FUTURE';
                    self::$currentTrade['sellingPrice'] = $closingPrice;
                    self::$currentTrade['symbol'] = $symbol;
                    self::$currentTrade['interval'] = self::$interval;
                    self::$currentTrade['profit'] = $profit;
                    self::$currentTrade['lowestPrice'] = self::$extremePrice;
                    self::$currentTrade['liquidationPrice'] = 0;
                    self::$currentTrade['lowestPricePercentage'] = abs(((self::$open_price - self::$extremePrice) / self::$open_price)) * 100;
                    self::$currentTrade['position'] = self::$tradeType;
                    self::$currentTrade['formula'] = self::$formula;
                    self::$currentTrade['duration'] = ($data[$index]['binance_timestamp'] - $data[self::$openingIndex]['binance_timestamp']) / (1000 * 60);
                    self::$trades[] = self::$currentTrade;

                    // Log to console
                    error_log(self::$tradeType . " Entry " . self::$formulaType . " for {$symbol}: " . self::$currentTrade['profit']);

                    // Resetting params
                    self::resetTradeParams();
                }
            }
        }


        if ($index == (count($data) - 1) && !empty(self::$currentTrade)) {
            self::$leftovers[] = $symbol;
        }
        self::confirmOpening($symbol, 'TBD', $data, $index, 'TBD');
        return self::$trades;
    }



    // ###############################################################################
    //                               Base Opening Logic
    // ###############################################################################

    public static function handleOpeningConditions($symbol, $data, $index)
    {
        // Entry Using SR Levels
        // $srEntry = self::checkSRLevelsEntry($symbol, $data, $index);
        // if ($srEntry) {
        //     self::$formulaType = 'SRLevels';
        //     return $srEntry;
        // }

        // Entry Using FVG Levels
        $fvgEnry = self::checkFVGZoneEntry($symbol, $data, $index);
        if ($fvgEnry) {
            self::$formulaType = 'FVGZone';
            return $fvgEnry;
        }
        return null;
    }






    // ###############################################################################
    //                 Opening Conditions Supply Demand Zones
    // ###############################################################################

    public static function checkSRLevelsEntry($symbol, $data, $index)
    {
        $latestZoneS = null;

        if (!count(self::$sLevels)) {
            return null;
        }

        // Record First time entry
        $bodyMin = min($data[$index]['close'], $data[$index]['open']);
        $bodyMax = max($data[$index]['close'], $data[$index]['open']);
        $high = $data[$index]['high'];
        $low = $data[$index]['low'];

        if (count(self::$sLevels))
            $latestZoneS = self::$sLevels[count(self::$sLevels) - 1];


        if (!self::$currentZoneStatus) {

            // If entered zone
            if (
                $latestZoneS
                && $bodyMax <= $latestZoneS['top']
                && $bodyMin >= $latestZoneS['bottom']
            ) {
                self::$currentZoneStatus = [
                    'zoneType' => 'support',
                    'currentZone' => $latestZoneS,
                    'entryCount' => 1,
                    'type' => null,
                    'exitCount' => 0,
                    'entryTimestamp' => $data[$index]['binance_timestamp'],
                    'entryIndex' => $index,
                    'exitIndex' => null,
                    'exitTimestamp' => null,
                    'reEntryIndex' => null,
                    'reEntryTimestamp' => null,
                ];
                return null;
            }
        }

        // Now record rest of exit and entry status
        if (self::$currentZoneStatus) {

            $top = self::$currentZoneStatus['currentZone']['top'];
            $bottom = self::$currentZoneStatus['currentZone']['bottom'];


            if (self::$currentZoneStatus['entryCount'] == 1 && self::$currentZoneStatus['exitCount'] == 0) {
                // Check for exit
                if (
                    $bodyMax > $top
                ) {

                    self::$currentZoneStatus['type'] = 'LONG';
                    self::$currentZoneStatus['exitCount'] = 1;
                    self::$currentZoneStatus['exitIndex'] = $index;
                    self::$currentZoneStatus['exitTimestamp'] = $data[$index]['binance_timestamp'];
                    return null;
                }
                if (
                    $bodyMin < $bottom
                ) {

                    self::$currentZoneStatus['type'] = 'SHORT';
                    self::$currentZoneStatus['exitCount'] = 1;
                    self::$currentZoneStatus['exitIndex'] = $index;
                    self::$currentZoneStatus['exitTimestamp'] = $data[$index]['binance_timestamp'];
                    return null;
                }
            }


            if (self::$currentZoneStatus['entryCount'] == 1 && self::$currentZoneStatus['exitCount'] == 1) {
                // Check for re entry
                if (
                    $bodyMax <= $top
                    && $bodyMin >= $bottom
                ) {
                    self::$currentZoneStatus['entryCount'] = 2;
                    self::$currentZoneStatus['reEntryIndex'] = $index;
                    self::$currentZoneStatus['reEntryTimestamp'] = $data[$index]['binance_timestamp'];
                    return null;
                }
            }

            // Check for final exit and enter LONG
            if (self::$currentZoneStatus['entryCount'] == 2 && self::$currentZoneStatus['exitCount'] == 1) {

                $midPoint = ($bodyMax + $bodyMin) / 2;
                if (
                    $midPoint > $top && self::$currentZoneStatus['type'] === 'LONG'
                ) {
                    self::$dynamicSL = $bottom;
                    self::$dynamicTP = $data[$index]['close'] + ($top - $bottom) * 2;

                    return "LONG";
                }

                if (
                    $midPoint < $bottom && self::$currentZoneStatus['type'] === 'SHORT'
                ) {
                    self::$dynamicSL = $top;
                    self::$dynamicTP = $data[$index]['close'] - ($top - $bottom) * 2;

                    return "SHORT";
                }
            }
        }


        // Logic to stop looking for retracements when entered in new zone
        if (count(self::$sLevels))
            $latestZoneS = self::$sLevels[count(self::$sLevels) - 1];

        if (self::$currentZoneStatus) {
            // If entered zone
            if (
                $bodyMax <= $latestZoneS['top']
                && $bodyMin >= $latestZoneS['bottom']
                && self::$currentZoneStatus['currentZone']['timestamp'] < $latestZoneS['timestamp']
            ) {
                self::$currentZoneStatus = [
                    'zoneType' => 'support',
                    'currentZone' => $latestZoneS,
                    'entryCount' => 1,
                    'type' => null,
                    'exitCount' => 0,
                    'entryTimestamp' => $data[$index]['binance_timestamp'],
                    'entryIndex' => $index,
                    'exitIndex' => null,
                    'exitTimestamp' => null,
                    'reEntryIndex' => null,
                    'reEntryTimestamp' => null,
                ];
                return null;
            }
        }
    }






    // ###############################################################################
    //                      Opening Conditions FVG Zones
    // ###############################################################################

    public static function checkFVGZoneEntry($symbol, $data, $index)
    {

        $fvg = self::$currentFVG;

        if ($fvg) {
            if ($fvg['type'] === 'bullish') {

                if (
                    $data[$index]['close'] > $fvg['top']
                    && $data[$index]['low'] < $fvg['top']
                ) {

                    self::$dynamicSL = $fvg['bottom'] * (1 - 0.2 / 100);
                    self::$dynamicTP = $data[$index]['close'] + (($data[$index]['close'] -  self::$dynamicSL) * 2);
                    return 'LONG';
                }
            } else {

                if (
                    $data[$index]['close'] < $fvg['bottom']
                    && $data[$index]['high'] > $fvg['bottom']
                ) {


                    self::$dynamicSL = $fvg['top'] * (1 + 0.2 / 100);
                    self::$dynamicTP = $data[$index]['close'] - ((self::$dynamicSL - $data[$index]['close']) * 2);
                    return 'SHORT';
                }
            }
        }
    }








    // ###############################################################################
    //                         Closing Logic
    // ###############################################################################


    public static function handleClosingConditions($symbol, $data, $index)
    {
        $closingPrice = 0;
        if (self::$tradeType === 'LONG') {
            if ($data[$index]['high'] >= self::$dynamicTP) {
                $closingPrice = self::$dynamicTP;
            } else if ($data[$index]['low'] < self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        } else {
            if ($data[$index]['low'] <= self::$dynamicTP) {
                $closingPrice = self::$dynamicTP;
            } else if ($data[$index]['high'] > self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        }
        return $closingPrice;
    }











    // ###############################################################################
    //                         Levels Adjustment for global system
    // ###############################################################################



    public static function setCurrentFVG($data, $data1hRaw, $index)
    {
        $data1h = CommonHelpers::filterCandlestickData($data1hRaw, null, $data[$index]['binance_timestamp']);
        $index1h = count($data1h) - 2;
        $fvg = CommonHelpers::getLatestFVGatIndex($data1h, $index1h);
        self::$currentFVG = $fvg;

        return true;
    }
    public static function setSRLevels($data, $data4hRaw, $index)
    {
        $data4h = CommonHelpers::filterCandlestickData($data4hRaw, null, $data[$index]['binance_timestamp']);
        $index4h = count($data4h) - 2;

        $depth = 3;
        $loopIndex = $index4h - $depth;
        while ($loopIndex > 10) {

            $pivot = CommonHelpers::checkPivot($data4h, $loopIndex, $depth);

            if ($pivot === 'high_pivot') {


                if (count(self::$rLevels)) {
                    $lastR = self::$rLevels[count(self::$rLevels) - 1];

                    if ($lastR['timestamp'] < $data4h[$loopIndex]['binance_timestamp']) {
                        self::$rLevels[] = [
                            'top' => $data4h[$loopIndex]['high'],
                            'bottom' => $data4h[$loopIndex]['low'],
                            'timestamp' => $data4h[$loopIndex]['binance_timestamp'],
                            'timestamp_pst' => $data4h[$loopIndex]['timestamp_pst'],
                            'timestampReadable' => $data4h[$loopIndex]['timestampReadable'],

                        ];
                    }
                } else {
                    self::$rLevels[] = [
                        'top' => $data4h[$loopIndex]['high'],
                        'bottom' => $data4h[$loopIndex]['low'],
                        'timestamp' => $data4h[$loopIndex]['binance_timestamp'],
                        'timestamp_pst' => $data4h[$loopIndex]['timestamp_pst'],
                        'timestampReadable' => $data4h[$loopIndex]['timestampReadable'],

                    ];
                }
            }


            if ($pivot === 'low_pivot') {

                if (count(self::$sLevels)) {
                    $lastS = self::$sLevels[count(self::$sLevels) - 1];

                    if ($lastS['timestamp'] < $data4h[$loopIndex]['binance_timestamp']) {
                        self::$sLevels[] = [
                            'top' => $data4h[$loopIndex]['high'],
                            'bottom' =>  $data4h[$loopIndex]['low'],
                            'timestamp' => $data4h[$loopIndex]['binance_timestamp'],
                            'timestamp_pst' => $data4h[$loopIndex]['timestamp_pst'],
                            'timestampReadable' => $data4h[$loopIndex]['timestampReadable'],
                        ];
                    }
                } else {
                    self::$sLevels[] = [
                        'top' => $data4h[$loopIndex]['high'],
                        'bottom' =>  $data4h[$loopIndex]['low'],
                        'timestamp' => $data4h[$loopIndex]['binance_timestamp'],
                        'timestamp_pst' => $data4h[$loopIndex]['timestamp_pst'],
                        'timestampReadable' => $data4h[$loopIndex]['timestampReadable'],

                    ];
                }
            }


            $loopIndex--;
        }
        return true;
    }



    // ###############################################################################
    //                         Helpers for confirmed Trade
    // ###############################################################################


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

        $id =  DB::table('confirmed_trades')->insertGetId([
            'coin_name' => $symbol,
            'type' => $type,
            'intention' => $intention,
            'formula' => self::$formula,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'checkpoint_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => $candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => null,
            'bolling_last_squeezed_timestamp' => null,
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

    public static function resetTradeParams()
    {
        self::$extremePrice = 0;
        self::$currentTrade = [];
        self::$open_price = 0;
        self::$tradeType = null;
        self::$openingIndex = 0;
        self::$dynamicTP = 0;
        self::$dynamicSL = 0;
        self::$formulaType = null;
        self::$currentZoneStatus = null;
    }
    // ##########################################################################
    //                          Happy Coding !😊
    // ##########################################################################
}
