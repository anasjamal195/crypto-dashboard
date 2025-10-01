<?php

namespace App\Services\LiveTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\OpeningConditionServiceLive;
use App\Services\OrderBlockService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SOLUSDT
{


    // Control Panel for major params
    public static $symbol = 'SOLUSDT';
    public static $interval = '15m';
    public static $strategy_name = 'ZONES_FVGS_COMBINED';
    public static $minAllowedRatio = 1;
    public static $account_id = 2;

    /**
     * Create a new class instance.
     */
    public function __construct() {}


    public static function runTrader($testModeOptions = null)
    {




        $current_system_time = (int) round(microtime(true) * 1000);
        $strategy_name = self::$strategy_name;
        $symbol = self::$symbol;
        $interval = self::$interval;
        $timestamp = null;
        $account_id = self::$account_id;

        $data = null;
        $data4hRaw = null;
        $data1hRaw = null;
        $index = null;
        $enabledStrategies = [
            'TRENDLINE',
            'DOUBLE_BREAKOUTS',
            'FVG',
            'ORDERBLOCK',
            'AGGRESSIVE'
        ];


        // Check if testmode is enabled for debugging
        if ($testModeOptions) {
            $data = $testModeOptions['data'];
            $index = $testModeOptions['index'];
            $data4hRawComplete = $testModeOptions['data4hRaw'];
            $data4hRaw = CommonHelpers::filterCandlestickData($data4hRawComplete, null, $data[$index]['binance_timestamp']);
            $data1hRawComplete = $testModeOptions['data1hRaw'];
            $data1hRaw = CommonHelpers::filterCandlestickData($data1hRawComplete, null, $data[$index]['binance_timestamp']);
            $enabledStrategies = $testModeOptions['enabledStrategies'];
            if ($testModeOptions['zoneProcessing'])
                self::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);
        } else {
            $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, 1000, $timestamp, 'FUTURE');
            $data1hRaw = BinanceApiService::getCandleStickDataPast($symbol, '1h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
            $data4hRaw = BinanceApiService::getCandleStickDataPast($symbol, '4h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
            $index = count($data) - 2;
            self::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);
        }




        Log::info('SOLUSDT 15m Triggered', [
            'system_time' => $current_system_time,
            'candle_time' => $data[$index]['binance_timestamp'],
        ]);





        $tradeSetupDetails = null;




        // TRENDLINE ENTRIES SETUP
        if (!$tradeSetupDetails && in_array('TRENDLINE', $enabledStrategies)) {

            $pivotDepth = 3;
            $trendlines = self::getRecentTrendlines($data, $index, $pivotDepth);
            $recentTrendLineResistance = $trendlines['resistanceTrendline'];
            $recentTrendLineSupport = $trendlines['supportTrendline'];

            $topZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'top_zone')->first()), true);
            $middleZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'middle_zone')->first()), true);
            $bottomZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'bottom_zone')->first()), true);
            $currentZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('status', 'active')->first()), true);


            $data4h = CommonHelpers::filterCandlestickData($data4hRaw, null, $data[$index]['binance_timestamp']);

            $candle4h = $data4h[count($data4h) - 2];
            // Check for SHORT setup (resistance breakout/rejection) with a negative slope
            if ($recentTrendLineResistance && $recentTrendLineResistance['m'] < 0 ) {

                $opening = true;

                $breakoutPrice = CommonHelpers::getBreakoutPriceFromTrendLine($data, $index, $recentTrendLineResistance);

                if (
                    $data[$index]['close'] < $breakoutPrice
                    && $data[$index]['open'] > $breakoutPrice
                    // && CommonHelpers::getPercentDiff($breakoutPrice, $data[$index]['close']) >= 0.05
                ) {


                    $sl = null;
                    $tp = null;

                    $entryPrice = $data[$index]['close'];

                    // Search for recent pivot high for SL
                    $recentHighPivot = CommonHelpers::getRecentPivot($data, $index, 'high', 1, 'wick');


                    if ($recentHighPivot) {

                        $sl = $recentHighPivot['value'];
                        $tp = $entryPrice - abs($entryPrice - $sl) * 1.3;
                    }

                    // --- TP FILTER (Reduced minimum percentage gain to 0.2%) ---
                    if (
                        CommonHelpers::getPercentDiff($entryPrice, $tp) < 0.2 // Changed from 0.5 to 0.2

                        // ||
                        // ($recentTrendLineResistance && $recentTrendLineSupport)
                    ) {
                        $opening = false;
                    }


                    $current_atr = $data[$index]['atr14'];
                    // --- ATR FILTER (Reduced multiplier from 3 to 1.5) ---
                    // We require the SL distance to be at least 1.5 times the current 14-period ATR
                    $min_sl_distance_atr = $current_atr * 1.5; // Changed from 3 to 1.5
                    $sl_price = $sl;
                    $sl_distance = abs($entryPrice - $sl_price);

                    // --- ATR FILTER FOR SHORT ---
                    if ($sl_distance < $min_sl_distance_atr) {
                        // Fail Reason: The trade is too tight (Stop-Loss is too close) relative to current market volatility.
                        $opening = false;
                        // Log::info("TRENDLINE: SHORT filtered out due to SL distance ({$sl_distance}) being less than 1.5x ATR ({$min_sl_distance_atr})");
                    }



                    if ($opening)
                        $tradeSetupDetails = [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'direction' => 'SHORT',
                            'tp' => $tp,
                            'sl' => $sl,
                            'trigger_price' => $entryPrice,
                            'opening_rule' => 'immidiate_opening',
                            'zones' => json_encode([
                                'top_zone' => $topZone,
                                'middle_zone' => $middleZone,
                                'bottom_zone' => $bottomZone
                            ]),
                            'fvg' => null,
                            'current_zone' => $currentZone ? json_encode($currentZone) : null,
                            'status' => 'WAITING',
                            'account_id' => $account_id,
                            'candle_timestamp' => $data[$index]['binance_timestamp'],
                            'timestamp' => $current_system_time,
                            'strategy_name' => 'TRENDLINE',
                            'trendline' => json_encode([
                                'resistance' => $recentTrendLineResistance,
                                'support' => $recentTrendLineSupport,
                            ]),
                            'orderblock' => null,

                        ];
                }
            }

            // Check for LONG setup (support breakout/rejection) with a positive slope
            if ($recentTrendLineSupport && $recentTrendLineSupport['m'] > 0 ) {

                $opening = true;
                $breakoutPrice = CommonHelpers::getBreakoutPriceFromTrendLine($data, $index, $recentTrendLineSupport);

                if (
                    $data[$index]['close'] > $breakoutPrice
                    && $data[$index]['open'] < $breakoutPrice
                    // && CommonHelpers::getPercentDiff($breakoutPrice, $data[$index]['close']) >= 0.05
                ) {


                    $sl = null;
                    $tp = null;

                    $entryPrice = $data[$index]['close'];

                    // Search for recent pivot low for SL
                    $recentLowPivot = CommonHelpers::getRecentPivot($data, $index, 'low', 1, 'wick');

                    if ($recentLowPivot) {

                        $sl = $recentLowPivot['value'];
                        $tp = $entryPrice + abs($entryPrice - $sl) * 1.3;
                    }

                    // --- TP FILTER (Reduced minimum percentage gain to 0.2%) ---
                    if (
                        CommonHelpers::getPercentDiff($entryPrice, $tp) < 0.2 // Changed from 0.5 to 0.2
                        // ||
                        // ($recentTrendLineResistance && $recentTrendLineSupport)
                    ) {
                        $opening =  false;
                    }

                    $current_atr = $data[$index]['atr14'];
                    // --- ATR FILTER (Reduced multiplier from 3 to 1.5) ---
                    // We require the SL distance to be at least 1.5 times the current 14-period ATR
                    $min_sl_distance_atr = $current_atr * 1.5; // Changed from 3 to 1.5
                    $sl_price = $sl;
                    $sl_distance = abs($entryPrice - $sl_price);
                    $sl_percent_diff = CommonHelpers::getPercentDiff($entryPrice, $sl);

                    // --- ATR FILTER FOR LONG ---
                    if ($sl_distance < $min_sl_distance_atr) {
                        // Fail Reason: The trade is too tight (Stop-Loss is too close) relative to current market volatility.
                        $opening = false;
                        // Log::info("TRENDLINE: LONG filtered out due to SL distance ({$sl_distance}) being less than 1.5x ATR ({$min_sl_distance_atr})");
                    }

                    if ($opening)
                        $tradeSetupDetails = [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'direction' => 'LONG',
                            'tp' => $tp,
                            'sl' => $sl,
                            'trigger_price' => $entryPrice,
                            'opening_rule' => 'immidiate_opening',
                            'zones' => json_encode([
                                'top_zone' => $topZone,
                                'middle_zone' => $middleZone,
                                'bottom_zone' => $bottomZone
                            ]),
                            'fvg' => null,
                            'current_zone' => $currentZone ? json_encode($currentZone) : null,
                            'status' => 'WAITING',
                            'account_id' => $account_id,
                            'candle_timestamp' => $data[$index]['binance_timestamp'],
                            'timestamp' => $current_system_time,
                            'strategy_name' => 'TRENDLINE',
                            'trendline' => json_encode([
                                'resistance' => $recentTrendLineResistance,
                                'support' => $recentTrendLineSupport,
                            ]),
                            'orderblock' => null,

                        ];
                }
            }
        }


        if (
            $tradeSetupDetails
            // &&
            // ($minsTo1h != 45)
            // && CommonHelpers::getPercentDiff($tradeSetupDetails['trigger_price'], $tradeSetupDetails['sl']) < 1
        ) {
            DB::table('trade_setup_details')->insert($tradeSetupDetails);


            // Testing Mode options

            $tradeSetupDetails['top_zone'] = $tradeSetupDetails['zones'] ? json_decode($tradeSetupDetails['zones'], true)['top_zone'] : null;
            $tradeSetupDetails['bottom_zone'] = $tradeSetupDetails['zones'] ? json_decode($tradeSetupDetails['zones'], true)['bottom_zone'] : null;
            $tradeSetupDetails['middle_zone'] = $tradeSetupDetails['zones'] ? json_decode($tradeSetupDetails['zones'], true)['middle_zone'] : null;
            $tradeSetupDetails['fvg'] = $tradeSetupDetails['fvg'] ? json_decode($tradeSetupDetails['fvg'], true) : null;
            $tradeSetupDetails['orderblock'] = $tradeSetupDetails['orderblock'] ? json_decode($tradeSetupDetails['orderblock'], true) : null;
            $tradeSetupDetails['trendline'] = $tradeSetupDetails['trendline'] ? json_decode($tradeSetupDetails['trendline'], true) : null;
            $tradeSetupDetails['current_zone'] = $tradeSetupDetails['current_zone'] ? json_decode($tradeSetupDetails['current_zone'], true) : null;
            $tradeSetupDetails['signal_timestamp'] = $data[$index]['binance_timestamp'];



            return $tradeSetupDetails;
        }
        return null;
    }



    public static function updateZonesInDb($data, $index, $dataHigherRaw, $dataHigherFilterZoneRaw, $interval, $symbol)
    {
        return true;
    }

    public static function getCandle4h($data1hRaw, $candle15m)
    {
        $data = CommonHelpers::filterCandlestickData($data1hRaw, null, $candle15m['binance_timestamp']);
        $index = count($data) - 2;
        return $data[$index];
    }

    public static function getRecentTrendlines($data, $indexLast, $pivotDepth = 3, $minPivots = 1)
    {
        $index = $pivotDepth;
        $lowPivot = [];
        $highPivot = [];

        $recentTrendLineResistance = null;
        $recentTrendLineSupport = null;

        while ($index <= $indexLast) {

            $pivot = CommonHelpers::checkPivotIndicator($data, $index - $pivotDepth, $pivotDepth, null, 'low');

            if ($pivot === 'low_pivot') {

                if (count($lowPivot) <= ($minPivots - 1)) {
                    // store pivot as [index, price]
                    $lowPivot[] = [
                        'index' => $index - $pivotDepth,
                        'x' => $data[$index - $pivotDepth]['binance_timestamp'],
                        'y' => $data[$index - $pivotDepth]['low'],
                        'price' => $data[$index - $pivotDepth]['low']
                    ];
                } else {

                    $lastPivot = $lowPivot[count($lowPivot) - 1];
                    // Check for ascending support line (higher lows)
                    if ($lastPivot['price'] < $data[$index - $pivotDepth]['low']) {
                        $lowPivot[] = [
                            'index' => $index - $pivotDepth,
                            'x' => $data[$index - $pivotDepth]['binance_timestamp'],
                            'y' => $data[$index - $pivotDepth]['low'],
                            'price' => $data[$index - $pivotDepth]['low']
                        ];

                        $regression = CommonHelpers::linearRegression($lowPivot);
                        $m = $regression['m'];
                        $c = $regression['c'];
                        $recentTrendLineSupport = [
                            'm' => $m,
                            'c' => $c,
                            'startIndex' => $lowPivot[0]['index'],
                            'endIndex' => $lowPivot[count($lowPivot) - 1]['index'],
                        ];
                        // dd($regression);
                    } else {

                        $recentTrendLineSupport = null;
                        $lowPivot = [];
                    }
                }
            } else if ($pivot === 'high_pivot') {

                if (count($highPivot) <= ($minPivots - 1)) {
                    // store pivot as [index, price]
                    $highPivot[] = [
                        'index' => $index - $pivotDepth,
                        'x' => $data[$index - $pivotDepth]['binance_timestamp'],
                        'y' => $data[$index - $pivotDepth]['high'],
                        'price' => $data[$index - $pivotDepth]['high']
                    ];
                } else {

                    $lastPivot = $highPivot[count($highPivot) - 1];
                    // Check for descending resistance line (lower highs)
                    // FIX: Changed comparison from $data[...]['low'] to $data[...]['high']
                    if ($lastPivot['price'] > $data[$index - $pivotDepth]['high']) {
                        $highPivot[] = [
                            'index' => $index - $pivotDepth,
                            'x' => $data[$index - $pivotDepth]['binance_timestamp'],
                            'y' => $data[$index - $pivotDepth]['high'],
                            'price' => $data[$index - $pivotDepth]['high']
                        ];

                        $regression = CommonHelpers::linearRegression($highPivot);
                        $m = $regression['m'];
                        $c = $regression['c'];
                        $recentTrendLineResistance = [
                            'm' => $m,
                            'c' => $c,
                            'startIndex' => $highPivot[0]['index'],
                            'endIndex' => $highPivot[count($highPivot) - 1]['index'],
                        ];
                        // dd($regression);
                    } else {
                        $recentTrendLineResistance = null;
                        $highPivot = [];
                    }
                }
            }

            $index++;
        }


        return [
            'supportTrendline' => $recentTrendLineSupport,
            'resistanceTrendline' => $recentTrendLineResistance,
        ];
    }
}
