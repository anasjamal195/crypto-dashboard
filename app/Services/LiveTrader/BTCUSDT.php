<?php

namespace App\Services\LiveTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\OpeningConditionServiceLive;
use Illuminate\Support\Facades\DB;

class BTCUSDT
{


    // Control Panel for major params
    public static $symbol = 'BTCUSDT';
    public static $interval = '15m';
    public static $strategy_name = 'ZONES_FVGS_COMBINED';
    public static $minAllowedRatio = 1.3;
    public static $account_id = 2;

    /**
     * Create a new class instance.
     */
    public function __construct() {}


    public static function runTrader()
    {


        $current_system_time = (int) round(microtime(true) * 1000);
        $strategy_name = self::$strategy_name;
        $symbol = self::$symbol;
        $interval = self::$interval;
        $timestamp = null;
        $account_id = self::$account_id;
        $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, 1000, $timestamp, 'FUTURE');
        $data1hRaw = BinanceApiService::getCandleStickDataPast($symbol, '1h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
        $data4hRaw = BinanceApiService::getCandleStickDataPast($symbol, '4h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
        $index = count($data) - 2;
        self::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);





        $tradeSetupDetails = DB::table('trade_setup_details')->where('symbol', $symbol)->where('interval', $interval)->first();



        // SAFE ENTRIES SETUP
        if (!$tradeSetupDetails) {

            // Opening Handler Logic
            $currentActivity = self::getCurrentActivity($symbol, self::$interval, $data, $index);

            if ($currentActivity) {
                $topZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'top_zone')->first()), true);
                $middleZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'middle_zone')->first()), true);
                $bottomZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'bottom_zone')->first()), true);
                $currentZone = DB::table('sd_zones')->where('id', $currentActivity->zone_id)->first();


                if ($currentActivity->activity === 'low_break_down') {
                    $breakoutValue = $currentActivity->value;
                    $sl = null;
                    $tp = null;
                    $zoneTop = $currentZone->top;
                    $zoneMid = ($currentZone->top + $currentZone->bottom) / 2;
                    $zoneBottom = $currentZone->bottom;
                    $zoneSizePercent = CommonHelpers::getPercentDiff($currentZone->bottom, $currentZone->top);
                    if ($zoneSizePercent < 1) {
                        $sl = $zoneTop;
                    } else {
                        $sl = $zoneMid;
                    }
                    $nextZone = DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('top', '<=', $breakoutValue)->orderBy('top', 'DESC')->first();
                    $tpNextZone = ($nextZone->top + $nextZone->bottom) / 2;
                    $tp = $tpNextZone;

                    if (!isset($tp)) {
                        return null;
                    }
                    if (CommonHelpers::checkRR($breakoutValue, $tp, $sl, self::$minAllowedRatio))
                        $tradeSetupDetails = [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'direction' => 'SHORT',
                            'tp' => $tp,
                            'sl' => $sl,
                            'trigger_price' => $breakoutValue,
                            'opening_rule' => 'waiting_till_next_touch',
                            'zones' => json_encode([
                                'top_zone' => $topZone,
                                'middle_zone' => $middleZone,
                                'bottom_zone' => $bottomZone
                            ]),
                            'fvg' => null,
                            'current_zone' => json_encode($currentZone),
                            'status' => 'WAITING',
                            'account_id' => $account_id,
                            'candle_timestamp' => $data[$index]['binance_timestamp'],
                            'timestamp' => $current_system_time,
                            'strategy_name' => $strategy_name,
                        ];
                } else if ($currentActivity->activity === 'high_break_out') {



                    $breakoutValue = $currentActivity->value;


                    $sl = null;
                    $tp = null;






                    $zoneTop = $currentZone->top;
                    $zoneMid = ($currentZone->top + $currentZone->bottom) / 2;
                    $zoneBottom = $currentZone->bottom;

                    $zoneSizePercent = CommonHelpers::getPercentDiff($currentZone->bottom, $currentZone->top);




                    if ($zoneSizePercent < 1) {
                        $sl = $zoneBottom;
                    } else {
                        $sl = $zoneMid;
                    }


                    $nextZone = DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('bottom', '>', $breakoutValue)->orderBy('bottom', 'ASC')->first();


                    $tpNextZone = ($nextZone->bottom + $nextZone->top) / 2;


                    $tp = $tpNextZone;



                    if (!isset($tp)) {
                        return null;
                    }

                    if (CommonHelpers::checkRR($breakoutValue, $tp, $sl, self::$minAllowedRatio))
                        $tradeSetupDetails = [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'direction' => 'LONG',
                            'tp' => $tp,
                            'sl' => $sl,
                            'trigger_price' => $breakoutValue,
                            'opening_rule' => 'waiting_till_next_touch',
                            'zones' => json_encode([
                                'top_zone' => $topZone,
                                'middle_zone' => $middleZone,
                                'bottom_zone' => $bottomZone
                            ]),
                            'fvg' => null,
                            'current_zone' => json_encode($currentZone),
                            'status' => 'WAITING',
                            'account_id' => $account_id,
                            'candle_timestamp' => $data[$index]['binance_timestamp'],
                            'timestamp' => $current_system_time,
                            'strategy_name' => $strategy_name,
                        ];
                }
            }

            if ($tradeSetupDetails) {
                DB::table('trade_setup_details')->insert($tradeSetupDetails);
                return null;
            }
        }


        // FVG ENTRIES SETUP
        if (!$tradeSetupDetails) {

            $fvg = CommonHelpers::getLatestFVGatIndex($data, $index, 'body', 0.6, 50);
            $topZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'top_zone')->first()), true);
            $middleZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'middle_zone')->first()), true);
            $bottomZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'bottom_zone')->first()), true);


            if ($fvg) {
                $rangeIntersectCount = 0;
                $fvgCandle = $data[CommonHelpers::findIndexFromTimestamp($data, $index, $fvg['timestamp'])];

                if (
                    ($topZone && $bottomZone && $middleZone)
                ) {

                    if (CommonHelpers::rangesIntersect($fvgCandle['low'], $fvgCandle['high'], $topZone['bottom'], $topZone['top']))
                        $rangeIntersectCount++;
                    if (CommonHelpers::rangesIntersect($fvgCandle['low'], $fvgCandle['high'], $middleZone['bottom'], $middleZone['top']))
                        $rangeIntersectCount++;
                    if (CommonHelpers::rangesIntersect($fvgCandle['low'], $fvgCandle['high'], $bottomZone['bottom'], $bottomZone['top']))
                        $rangeIntersectCount++;
                }
                if (
                    ($topZone && $bottomZone && $middleZone)
                    && CommonHelpers::getPercentDiff($fvg['bottom'], $fvg['top'], true) < 1.5
                    && $rangeIntersectCount <= 1
                ) {


                    if ($fvg['type'] === 'bullish') {

                        if ((
                            $data[$index]['low'] < $fvg['top']
                            && $data[$index]['body_min'] > $fvg['top']
                            && $data[$index]['lower_wick'] > $data[$index]['upper_wick']
                        )) {
                            $entryPrice = $fvg['top'];
                            $sl = $fvg['bottom'] * (1 - 0.05 / 100);
                            $tp = $entryPrice + abs($entryPrice - $sl) * 2;

                            if (CommonHelpers::checkRR($entryPrice, $tp, $sl, self::$minAllowedRatio))
                                $tradeSetupDetails = [
                                    'symbol' => $symbol,
                                    'interval' => $interval,
                                    'direction' => 'LONG',
                                    'tp' => $tp,
                                    'sl' => $sl,
                                    'trigger_price' => $entryPrice,
                                    'opening_rule' => 'waiting_till_next_touch',
                                    'zones' => json_encode([
                                        'top_zone' => $topZone,
                                        'middle_zone' => $middleZone,
                                        'bottom_zone' => $bottomZone
                                    ]),
                                    'fvg' => json_encode($fvg),
                                    'current_zone' => null,
                                    'status' => 'WAITING',
                                    'account_id' => $account_id,
                                    'candle_timestamp' => $data[$index]['binance_timestamp'],
                                    'timestamp' => $current_system_time,
                                    'strategy_name' => $strategy_name,
                                ];
                        } else if (
                            (
                                $data[$index]['open'] < $fvg['top']
                                && $data[$index]['close'] > $fvg['top']
                            )
                        ) {
                            // LONG OPENING LOGIC

                            $entryPrice = $data[$index]['close'];
                            $sl = $fvg['bottom'] * (1 - 0.05 / 100);
                            $tp = $entryPrice + abs($entryPrice - $sl) * 2;
                            if (CommonHelpers::checkRR($entryPrice, $tp, $sl, self::$minAllowedRatio))
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
                                    'fvg' => json_encode($fvg),
                                    'current_zone' => null,
                                    'status' => 'WAITING',
                                    'account_id' => $account_id,
                                    'candle_timestamp' => $data[$index]['binance_timestamp'],
                                    'timestamp' => $current_system_time,
                                    'strategy_name' => $strategy_name,
                                ];
                        }
                    }
                    if ($fvg['type'] === 'bearish') {

                        if (
                            (
                                $data[$index]['high'] > $fvg['bottom']
                                && $data[$index]['body_max'] < $fvg['bottom']
                            )
                        ) {
                            $entryPrice = $fvg['bottom'];
                            $sl = $fvg['top'] * (1 + 0.05 / 100);
                            $tp = $entryPrice - abs($entryPrice - $sl) * 2;

                            if (CommonHelpers::checkRR($entryPrice, $tp, $sl, self::$minAllowedRatio))
                                $tradeSetupDetails = [
                                    'symbol' => $symbol,
                                    'interval' => $interval,
                                    'direction' => 'SHORT',
                                    'tp' => $tp,
                                    'sl' => $sl,
                                    'trigger_price' => $entryPrice,
                                    'opening_rule' => 'waiting_till_next_touch',
                                    'zones' => json_encode([
                                        'top_zone' => $topZone,
                                        'middle_zone' => $middleZone,
                                        'bottom_zone' => $bottomZone
                                    ]),
                                    'fvg' => json_encode($fvg),
                                    'current_zone' => null,
                                    'status' => 'WAITING',
                                    'account_id' => $account_id,
                                    'candle_timestamp' => $data[$index]['binance_timestamp'],
                                    'timestamp' => $current_system_time,
                                    'strategy_name' => $strategy_name,
                                ];
                        } else if (
                            (
                                $data[$index]['open'] > $fvg['bottom']
                                && $data[$index]['close'] < $fvg['bottom']
                            )
                        ) {

                            // SHORT OPENING LOGIC
                            $entryPrice = $data[$index]['close'];
                            $sl = $fvg['top'] * (1 + 0.05 / 100);
                            $tp = $entryPrice - abs($entryPrice - $sl) * 2;

                            if (CommonHelpers::checkRR($entryPrice, $tp, $sl, self::$minAllowedRatio))
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
                                    'fvg' => json_encode($fvg),
                                    'current_zone' => null,
                                    'status' => 'WAITING',
                                    'account_id' => $account_id,
                                    'candle_timestamp' => $data[$index]['binance_timestamp'],
                                    'timestamp' => $current_system_time,
                                    'strategy_name' => $strategy_name,
                                ];
                        }
                    }
                }
            }

            if ($tradeSetupDetails) {
                DB::table('trade_setup_details')->insert($tradeSetupDetails);
                return null;
            }
        }
    }



    public static function updateZonesInDb($data, $index, $dataHigherRaw, $dataHigherFilterZoneRaw, $interval, $symbol)
    {


        $allLevels = self::findZonesOn1h($dataHigherFilterZoneRaw, $dataHigherRaw, $data[$index], false);
        $candle15m = $data[$index];
        $currentPrice = $data[$index]['close'];


        $topZone = DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'top_zone')->first();
        $bottomZone = DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'bottom_zone')->first();
        $middleZone = DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('name', 'middle_zone')->first();


        if ($topZone && $bottomZone && $middleZone) {


            // Check which zone is currently active

            $activeZone = null;


            if (
                $currentPrice >= $topZone->bottom
                && $currentPrice <= $topZone->top
            ) {
                $activeZone = $topZone;
            }
            if (
                $currentPrice >= $bottomZone->bottom
                && $currentPrice <= $bottomZone->top
            ) {
                $activeZone = $bottomZone;
            }

            if (
                $currentPrice >= $middleZone->bottom
                && $currentPrice <= $middleZone->top
            ) {
                $activeZone = $middleZone;
            }




            // Check activities on active zone 
            if ($activeZone) {

                // Update in DB
                DB::table('sd_zones')->where('id', $activeZone->id)->update(
                    ['status' => 'active']
                );
                DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('id', '!=', $activeZone->id)->update(
                    ['status' => 'inactive']
                );
                if ($activeZone->aggressive_count == 0) {
                    DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->where('id', '!=', $activeZone->id)->update(
                        ['aggressive_count' => 0]
                    );
                }
                // New Zone is activated, add reentry activitiy
                if (
                    $data[$index]['open'] > $activeZone->top
                    ||
                    $data[$index]['open'] < $activeZone->bottom
                ) {

                    // Entry Confirmed


                    $entry_type = 'entry';

                    $recentActivity = self::getRecentActivity($symbol, self::$interval, $data, $index, null, ['lower_wick', 'upper_wick']);


                    if ($recentActivity && $recentActivity->zone_id != $activeZone->id) {
                        $entry_type = 'new_entry';
                    }


                    DB::table('sd_zones_activities')->insert(
                        [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'zone_id' => $activeZone->id,
                            'activity' => $entry_type,
                            'timestamp' => $data[$index]['binance_timestamp'],
                            'action' => 'none',
                        ]
                    );





                    $entry = self::getCurrentActivity($symbol, self::$interval, $data, $index, 'entry');


                    // CHECK FOR HIGH FORMATION


                    $sequence = [
                        'entry',
                        'break_out'
                    ];
                    if (self::verifyActivitySequence($symbol, $data, $index, $entry, $sequence)) {

                        // Validate this HIGH point

                        $firstEntry = self::getRecentActivity($symbol, self::$interval, $data, $index, 'new_entry');

                        if ($firstEntry) {
                            $breakout = self::getRecentActivity($symbol, self::$interval, $data, $index, 'break_out');

                            $entryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $entry->timestamp);
                            $breakoutIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $breakout->timestamp);


                            $high = $data[$breakoutIndex]['body_max'];
                            $highIndex = $breakoutIndex;
                            for ($i = $breakoutIndex; $i <= $entryIndex; $i++) {
                                if ($high < $data[$i]['body_max']) {
                                    $high = $data[$i]['body_max'];
                                    $highIndex = $i;
                                }
                            }


                            $firstEntryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $firstEntry->timestamp);


                            $isValid = true;
                            // Problematic part
                            if ($firstEntryIndex >= 0) {
                                for ($i = $firstEntryIndex; $i <= $index; $i++) {
                                    $actCurrent = self::getCurrentActivity($symbol, self::$interval, $data, $i, 'high');
                                    if ($actCurrent) {
                                        $isValid = false;
                                        break;
                                    }
                                }
                            } else {
                                $isValid = false;
                            }

                            if ($isValid) {
                                DB::table('sd_zones_activities')->insert(
                                    [
                                        'symbol' => $symbol,
                                        'interval' => $interval,
                                        'zone_id' => $activeZone->id,
                                        'activity' => 'high',
                                        'value' => $high,
                                        'timestamp' => $data[$highIndex]['binance_timestamp'],
                                        'action' => 'none',
                                    ]
                                );
                            }
                        }
                    }




                    // // CHECK FOR LOW FORMATION

                    $sequence = [
                        'entry',
                        'break_down'
                    ];



                    if (self::verifyActivitySequence($symbol, $data, $index, $entry, $sequence)) {

                        // Validate this low point

                        $firstEntry = self::getRecentActivity($symbol, self::$interval, $data, $index, 'new_entry');

                        if ($firstEntry) {
                            $breakdown = self::getRecentActivity($symbol, self::$interval, $data, $index, 'break_down');

                            $entryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $entry->timestamp);
                            $breakdownIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $breakdown->timestamp);


                            $low = $data[$breakdownIndex]['body_min'];
                            $lowIndex = $breakdownIndex;
                            for ($i = $breakdownIndex; $i <= $entryIndex; $i++) {
                                if ($low > $data[$i]['body_min']) {
                                    $low = $data[$i]['body_min'];
                                    $lowIndex = $i;
                                }
                            }


                            $firstEntryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $firstEntry->timestamp);



                            $isValid = true;

                            if ($firstEntryIndex >= 0) {
                                for ($i = $firstEntryIndex; $i <= $index; $i++) {
                                    $actCurrent = self::getCurrentActivity($symbol, self::$interval, $data, $i, 'low');
                                    if ($actCurrent) {
                                        $isValid = false;
                                        break;
                                    }
                                }
                            } else {
                                $isValid = false;
                            }

                            if ($isValid) {
                                DB::table('sd_zones_activities')->insert(
                                    [
                                        'symbol' => $symbol,
                                        'interval' => $interval,
                                        'zone_id' => $activeZone->id,
                                        'activity' => 'low',
                                        'value' => $low,
                                        'timestamp' => $data[$lowIndex]['binance_timestamp'],
                                        'action' => 'none',
                                    ]
                                );
                            }
                        }
                    }
                }
                // CommonHelpers::flushZones($symbol);

            } else {

                // Make Alll ZOnes inactive
                DB::table('sd_zones')->where('symbol', $symbol)->where('interval', $interval)->update(
                    ['status' => 'inactive']
                );




                // Confirm for exit activity

                $openPrice = $data[$index]['open'];



                $recentZone = null;
                if (
                    $openPrice >= $topZone->bottom
                    && $openPrice <= $topZone->top
                ) {
                    $recentZone = $topZone;
                }
                if (
                    $openPrice >= $bottomZone->bottom
                    && $openPrice <= $bottomZone->top
                ) {
                    $recentZone = $bottomZone;
                }

                if (
                    $openPrice >= $middleZone->bottom
                    && $openPrice <= $middleZone->top
                ) {
                    $recentZone = $middleZone;
                }


                if ($recentZone) {

                    DB::table('sd_zones_activities')->insert(
                        [
                            'symbol' => $symbol,
                            'interval' => $interval,
                            'zone_id' => $recentZone->id,
                            'activity' => $data[$index]['per'] > 0 ? 'break_out' : 'break_down',
                            'timestamp' => $data[$index]['binance_timestamp'],
                            'action' => 'none',
                        ]
                    );

                    // If breakout happens above top zone than reset top zone and delete very last zone
                    if ($data[$index]['per'] > 0 && $recentZone->id === $topZone->id) {
                        // Now Delete the bottom zone and find new top and rename zones
                        DB::table('sd_zones_activities')->where('zone_id', $bottomZone->id)->delete();
                        DB::table('sd_zones')->where('id', $bottomZone->id)->delete();


                        // Renaming existing zones

                        DB::table('sd_zones')->where('id', $middleZone->id)->update(
                            ['name' => 'bottom_zone']
                        );
                        DB::table('sd_zones')->where('id', $topZone->id)->update(
                            ['name' => 'middle_zone']
                        );

                        $activeZone = json_decode(json_encode($topZone), true);
                        $supplyZone =  self::findValidSupplyBlock($allLevels, $activeZone);
                        CommonHelpers::createOrUpdateZone($symbol, $interval, $supplyZone['type'], $supplyZone['top'], $supplyZone['bottom'], $supplyZone['timestamp_initial'], $supplyZone['timestamp_confirmed'], 'inactive', 'top_zone');
                    }


                    // If breakdown happens below bottom zone than reset bottom zone and delete very last zone

                    if ($data[$index]['per'] < 0 && $recentZone->id === $bottomZone->id) {
                        // Now Delete the bottom zone and find new top and rename zones
                        DB::table('sd_zones_activities')->where('zone_id', $bottomZone->id)->delete();
                        DB::table('sd_zones')->where('id', $bottomZone->id)->delete();


                        // Renaming existing zones

                        DB::table('sd_zones')->where('id', $middleZone->id)->update(
                            ['name' => 'top_zone']
                        );
                        DB::table('sd_zones')->where('id', $bottomZone->id)->update(
                            ['name' => 'middle_zone']
                        );

                        $activeZone = json_decode(json_encode($bottomZone), true);
                        $demandZone =  self::findValidSupplyBlock($allLevels, $activeZone);
                        CommonHelpers::createOrUpdateZone($symbol, $interval, $demandZone['type'], $demandZone['top'], $demandZone['bottom'], $demandZone['timestamp_initial'], $demandZone['timestamp_confirmed'], 'inactive', 'bottom_zone');
                    }
                } else {

                    // If no recent zone is detected, check for engulf

                    $engulfTop = self::setEngulfType($symbol, $topZone, $data, $index);

                    if ($engulfTop === 'engulf_break_out') {
                        // Now Delete the bottom zone and find new top and rename zones
                        DB::table('sd_zones_activities')->where('zone_id', $bottomZone->id)->delete();
                        DB::table('sd_zones')->where('id', $bottomZone->id)->delete();


                        // Renaming existing zones

                        DB::table('sd_zones')->where('id', $middleZone->id)->update(
                            ['name' => 'bottom_zone']
                        );
                        DB::table('sd_zones')->where('id', $topZone->id)->update(
                            ['name' => 'middle_zone']
                        );

                        $activeZone = json_decode(json_encode($topZone), true);
                        $supplyZone =  self::findValidSupplyBlock($allLevels, $activeZone);
                        CommonHelpers::createOrUpdateZone($symbol, $interval, $supplyZone['type'], $supplyZone['top'], $supplyZone['bottom'], $supplyZone['timestamp_initial'], $supplyZone['timestamp_confirmed'], 'inactive', 'top_zone');
                    }


                    self::setEngulfType($symbol, $middleZone, $data, $index);

                    $engulfBottom = self::setEngulfType($symbol, $bottomZone, $data, $index);


                    if ($engulfBottom === 'engulf_break_down') {
                        // Now Delete the bottom zone and find new top and rename zones
                        DB::table('sd_zones_activities')->where('zone_id', $bottomZone->id)->delete();
                        DB::table('sd_zones')->where('id', $bottomZone->id)->delete();


                        // Renaming existing zones

                        DB::table('sd_zones')->where('id', $middleZone->id)->update(
                            ['name' => 'top_zone']
                        );
                        DB::table('sd_zones')->where('id', $bottomZone->id)->update(
                            ['name' => 'middle_zone']
                        );

                        $activeZone = json_decode(json_encode($bottomZone), true);
                        $demandZone =  self::findValidSupplyBlock($allLevels, $activeZone);
                        CommonHelpers::createOrUpdateZone($symbol, $interval, $demandZone['type'], $demandZone['top'], $demandZone['bottom'], $demandZone['timestamp_initial'], $demandZone['timestamp_confirmed'], 'inactive', 'bottom_zone');
                    }
                    // Also Check for wick events
                    self::setWickType($symbol, $topZone, $data, $index);
                    self::setWickType($symbol, $middleZone, $data, $index);
                    self::setWickType($symbol, $bottomZone, $data, $index);
                }

                // Check for previous High Low Break

                self::setBreakoutsHigh($symbol, $data, $index);
                self::setBreakdownLow($symbol, $data, $index);
            }
        } else {

            CommonHelpers::flushZones($symbol);
            // Add Zones for first time only
            $activeZone = null;
            $demandZone = null;
            $supplyZone = null;

            foreach ($allLevels as $level) {

                if ($level['type'] === 'demand' && $level['bottom'] < $currentPrice) {
                    $demandZone = $level;
                }
                if ($level['type'] === 'supply' && $level['top'] > $currentPrice) {
                    $supplyZone = $level;
                }

                if ($demandZone && $supplyZone) {
                    break;
                }
            }


            if (!($demandZone && $supplyZone)) {
                $demandZone = null;
                $supplyZone = null;
                $activeZone = null;
                return null;
            }


            // Check for any zone activation
            if (
                $currentPrice >= $demandZone['bottom']
                && $currentPrice <= $demandZone['top']
            ) {
                $activeZone = $demandZone;
            }

            if (
                $currentPrice >= $supplyZone['bottom']
                && $currentPrice <= $supplyZone['top']
            ) {
                $activeZone = $supplyZone;
            }


            // If no active zone found
            if (!$activeZone) {
                return null;
            }


            $demandZone = self::findValidDemandBlock($allLevels, $activeZone);
            $supplyZone = self::findValidSupplyBlock($allLevels, $activeZone);



            // dd($activeZone, $demandZone, $supplyZone, $allLevels);
            // dd($activeZone, $demandZone, $supplyZone, $allLevels);

            // If No new demand supply formed
            if (!($demandZone && $supplyZone)) {
                $demandZone = null;
                $supplyZone = null;
                $activeZone = null;
                return null;
            }



            $activeZoneId = CommonHelpers::createOrUpdateZone($symbol, $interval, $activeZone['type'], $activeZone['top'], $activeZone['bottom'], $activeZone['timestamp_initial'], $activeZone['timestamp_confirmed'], 'active', 'middle_zone');

            DB::table('sd_zones_activities')->insert(
                [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'zone_id' => $activeZoneId,
                    'activity' => 'new_entry',
                    'timestamp' => $data[$index]['binance_timestamp'],
                    'action' => 'none',
                ]
            );
            CommonHelpers::createOrUpdateZone($symbol, $interval, $supplyZone['type'], $supplyZone['top'], $supplyZone['bottom'], $supplyZone['timestamp_initial'], $supplyZone['timestamp_confirmed'], 'inactive', 'top_zone');
            CommonHelpers::createOrUpdateZone($symbol, $interval, $demandZone['type'], $demandZone['top'], $demandZone['bottom'], $demandZone['timestamp_initial'], $demandZone['timestamp_confirmed'], 'inactive', 'bottom_zone');


            return true;
        }
    }
    public static function getCurrentActivity($symbol, $interval, $data, $index, $activity = null)
    {

        if ($activity) {
            $activity = DB::table('sd_zones_activities')->where('symbol', $symbol)->where('interval', $interval)->where('timestamp', $data[$index]['binance_timestamp'])->where('activity', $activity)->orderBy('timestamp', 'DESC')->first();
            return $activity;
        } else {
            $activity = DB::table('sd_zones_activities')->where('symbol', $symbol)->where('interval', $interval)->where('timestamp', $data[$index]['binance_timestamp'])->orderBy('timestamp', 'DESC')->first();
            return $activity;
        }
    }
    public static function getRecentActivity($symbol, $interval, $data, $index, $activity = null, $exceptions = [])
    {
        if ($activity) {
            $activity = DB::table('sd_zones_activities')->where('symbol', $symbol)->where('interval', $interval)->where('timestamp', '<=', $data[$index]['binance_timestamp'])->where('activity', $activity)->whereNotIn('activity', $exceptions)->orderBy('timestamp', 'DESC')->first();
            return $activity;
        } else {
            $activity = DB::table('sd_zones_activities')->where('symbol', $symbol)->where('interval', $interval)->where('timestamp', '<=', $data[$index]['binance_timestamp'])->whereNotIn('activity', $exceptions)->orderBy('timestamp', 'DESC')->first();
            return $activity;
        }
    }
    public static function verifyActivitySequence($symbol, $data, $index, $activity, $sequenceArr = [])
    {



        if (empty($sequenceArr)) {
            return false;
        }
        if (!$activity) {
            return false;
        }
        $activityIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $activity->timestamp, self::$interval);

        foreach ($sequenceArr as $sequenceIndex => $sequence) {
            $recentActivity = self::getRecentActivity($symbol, self::$interval, $data, $activityIndex);

            if (!$recentActivity || $recentActivity->activity !== $sequence) {
                return false;
            }

            $activityIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $recentActivity->timestamp, self::$interval)  - 1;
        }

        return true;
    }
    public static function getCandle4h($data1hRaw, $candle15m)
    {
        $data = CommonHelpers::filterCandlestickData($data1hRaw, null, $candle15m['binance_timestamp']);
        $index = count($data) - 2;
        return $data[$index];
    }
    public static function setBreakoutsHigh($symbol,  $data, $index)
    {

        $recentHigh = self::getRecentActivity($symbol, self::$interval, $data, $index, 'high');


        $firstEntry  = self::getRecentActivity($symbol, self::$interval, $data, $index, 'new_entry');

        if ($firstEntry && $recentHigh) {
            $validation = true;
            $entryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $firstEntry->timestamp);
            for ($i = $entryIndex; $i <= $index; $i++) {
                $actCurrent = self::getCurrentActivity($symbol, self::$interval, $data, $i, 'high_break_out');
                if ($actCurrent) {
                    $validation = false;
                    break;
                }
            }

            if ($validation && $recentHigh->timestamp >= $firstEntry->timestamp) {
                if ($data[$index]['close'] > $recentHigh->value && $data[$index]['open'] < $recentHigh->value) {
                    DB::table('sd_zones_activities')->insert(
                        [
                            'symbol' => $symbol,
                            'interval' => self::$interval,
                            'zone_id' => $recentHigh->zone_id,
                            'activity' => 'high_break_out',
                            'value' => $recentHigh->value,
                            'timestamp' => $data[$index]['binance_timestamp'],
                            'action' => 'none',
                        ]
                    );
                }
            }
        }
    }
    public static function setBreakdownLow($symbol,  $data, $index)
    {


        $recentLow = self::getRecentActivity($symbol, self::$interval, $data, $index, 'low');


        $firstEntry  = self::getRecentActivity($symbol, self::$interval, $data, $index, 'new_entry');

        if ($firstEntry && $recentLow) {
            $validation = true;
            $entryIndex = CommonHelpers::findIndexFromTimestamp($data, $index, $firstEntry->timestamp);
            for ($i = $entryIndex; $i <= $index; $i++) {
                $actCurrent = self::getCurrentActivity($symbol, self::$interval, $data, $i, 'low_break_down');
                if ($actCurrent) {
                    $validation = false;
                    break;
                }
            }


            if ($validation && $recentLow->timestamp >= $firstEntry->timestamp) {
                if ($data[$index]['close'] < $recentLow->value && $data[$index]['open'] > $recentLow->value) {
                    DB::table('sd_zones_activities')->insert(
                        [
                            'symbol' => $symbol,
                            'interval' => self::$interval,
                            'zone_id' => $recentLow->zone_id,
                            'activity' => 'low_break_down',
                            'value' => $recentLow->value,
                            'timestamp' => $data[$index]['binance_timestamp'],
                            'action' => 'none',
                        ]
                    );
                }
            }
        }
    }
    public static function setWickType($symbol, $zone, $data, $index)
    {



        $recentActivity = self::getRecentActivity($symbol, self::$interval, $data, $index - 1);



        if (
            $data[$index]['body_max'] < $zone->bottom
            && $data[$index]['high'] > $zone->bottom
        ) {
            if (
                $recentActivity
                && $recentActivity->activity === 'upper_wick'
                && $zone->id === $recentActivity->zone_id
            ) {
                return null;
            }
            DB::table('sd_zones_activities')->insert(
                [
                    'symbol' => $symbol,
                    'interval' => self::$interval,
                    'zone_id' => $zone->id,
                    'activity' => 'upper_wick',
                    'timestamp' => $data[$index]['binance_timestamp'],
                    'action' => 'none',
                ]
            );

            if ($zone->aggressive_count == 0) {
                DB::table('sd_zones')->where('symbol', $symbol)->where('interval', self::$interval)->where('id', '!=', $zone->id)->update(
                    ['aggressive_count' => 0]
                );
            }



            return 'upper_wick';
        }
        if (
            $data[$index]['body_min'] > $zone->top
            && $data[$index]['low'] < $zone->top

        ) {
            if (
                $recentActivity
                && $recentActivity->activity === 'lower_wick'
            ) {
                return null;
            }
            DB::table('sd_zones_activities')->insert(
                [
                    'symbol' => $symbol,
                    'interval' => self::$interval,
                    'zone_id' => $zone->id,
                    'activity' => 'lower_wick',
                    'timestamp' => $data[$index]['binance_timestamp'],
                    'action' => 'none',
                ]
            );

            if ($zone->aggressive_count == 0) {
                DB::table('sd_zones')->where('symbol', $symbol)->where('interval', self::$interval)->where('id', '!=', $zone->id)->update(
                    ['aggressive_count' => 0]
                );
            }


            return 'lower_wick';
        }
    }
    public static function setEngulfType($symbol, $zone, $data, $index)
    {
        if (
            (
                $data[$index]['open'] > $zone->top
                && $data[$index]['close'] < $zone->bottom)

            ||
            (
                $data[$index]['close'] > $zone->top
                && $data[$index]['open'] < $zone->bottom
            )

        ) {
            DB::table('sd_zones_activities')->insert(
                [
                    'symbol' => $symbol,
                    'interval' => self::$interval,
                    'zone_id' => $zone->id,
                    'activity' => $data[$index]['per'] > 0 ? 'engulf_break_out' : 'engulf_break_down',
                    'timestamp' => $data[$index]['binance_timestamp'],
                    'action' => 'none',
                ]
            );

            return $data[$index]['per'] > 0 ? 'engulf_break_out' : 'engulf_break_down';
        }
    }
    public static function findValidSupplyBlock($allLevels, $activeZone, $paddingMarginPercent = 0.2)
    {


        $paddingMargin = $activeZone['top'] * ($paddingMarginPercent / 100);

        $topLim = $activeZone['top'];
        $bottomLim = $activeZone['bottom'];

        $supplyZone = null;
        foreach ($allLevels as $index => $level) {

            if ($index >= (count($allLevels) - 2)) {
                break;
            }
            if (
                $level['bottom'] >= ($topLim + $paddingMargin)
                && $level['timestamp_initial'] !== $activeZone['timestamp_initial']
            ) {




                // If Zone found, validate it
                $prevLevel = $allLevels[$index + 1];

                $levelLow = $level['bottom'] - $paddingMargin;
                $levelHigh = $level['top'] + $paddingMargin;
                if (
                    $prevLevel['top'] < $levelLow
                    ||
                    $prevLevel['bottom'] > $levelHigh
                ) {
                    // Level is valid
                    $supplyZone = $level;
                    break;
                } else {
                    continue;
                }
            }
        }


        if (!$supplyZone) {
            $supplyZone = [
                'type' => 'supply',
                'top' =>  $activeZone['top'] * (1 + 0.1 / 100),
                'bottom' => $activeZone['top'] * (1 + 0.5 / 100),
                'timestamp_initial' => $activeZone['timestamp_confirmed'],
                'timestamp_confirmed' => $activeZone['timestamp_confirmed'],
                'is_imaginary' => true,
            ];
        }

        return $supplyZone;
    }
    public static function findValidDemandBlock($allLevels, $activeZone, $paddingMarginPercent = 0.2)
    {

        $paddingMargin = $activeZone['top'] * ($paddingMarginPercent / 100);
        $topLim = $activeZone['top'];
        $bottomLim = $activeZone['bottom'];

        $demandZone = null;
        foreach ($allLevels as $index => $level) {

            if ($index >= (count($allLevels) - 2)) {
                break;
            }
            if (
                $level['top'] <= ($bottomLim - $paddingMargin)
                && $level['timestamp_initial'] !== $activeZone['timestamp_initial']
            ) {


                // If Zone found, validate it
                $prevLevel = $allLevels[$index + 1];

                $levelLow = $level['bottom'] - $paddingMargin;
                $levelHigh = $level['top'] + $paddingMargin;
                if (

                    $prevLevel['top'] < $levelLow
                    ||
                    $prevLevel['bottom'] > $levelHigh

                ) {
                    // Level is valid
                    $demandZone = $level;
                    break;
                } else {
                    continue;
                }
            }
        }

        if (!$demandZone) {
            $demandZone = [
                'type' => 'demand',
                'top' =>  $activeZone['bottom'] * (1 - 0.1 / 100),
                'bottom' => $activeZone['bottom'] * (1 - 0.5 / 100),
                'timestamp_initial' => $activeZone['timestamp_confirmed'],
                'timestamp_confirmed' => $activeZone['timestamp_confirmed'],
                'is_imaginary' => true,
            ];
        }
        return $demandZone;
    }
    public static function findZonesOn1h($data4hRaw, $data1hRaw, $candle15m, $filterOnHigher = true)
    {

        $data = CommonHelpers::filterCandlestickData($data1hRaw, null, $candle15m['binance_timestamp']);
        $index = count($data) - 2;

        // Get All 4h Levels in timestamp ASC order
        $allLevels = [];


        $loopIndex = $index - 4;
        $demandZone = 0;
        $supplyCount = 0;

        $dataLength = count($data);
        while ($loopIndex > 4) {
            $pivot = CommonHelpers::checkPivot($data, $loopIndex, 4);

            if ($pivot === 'high_pivot') {


                $bottom = null;

                $wickSize = $data[$loopIndex]['high'] - max($data[$loopIndex]['close'], $data[$loopIndex]['open']);
                $bodySize = max($data[$loopIndex]['close'], $data[$loopIndex]['open']) - min($data[$loopIndex]['close'], $data[$loopIndex]['open']);



                if ($wickSize > $bodySize) {
                    $bottom = max($data[$loopIndex]['close'], $data[$loopIndex]['open']);
                } else if ($wickSize <= $bodySize) {
                    $bottom = min($data[$loopIndex]['close'], $data[$loopIndex]['open']);
                }

                $zoneSizePercent = CommonHelpers::getPercentDiff($bottom, $data[$loopIndex]['high']);

                if ($zoneSizePercent >= 2) {
                    $loopIndex--;

                    continue;
                }


                $allLevels[] = [
                    'type' => 'supply',
                    'top' => $data[$loopIndex]['high'],
                    'bottom' =>  $bottom,
                    'timestamp_initial' => $data[$loopIndex]['binance_timestamp'],
                    'timestamp_confirmed' => $data[$loopIndex + 4]['binance_timestamp'],
                ];
            }

            if ($pivot === 'low_pivot') {

                $top = null;


                $wickSize = min($data[$loopIndex]['close'], $data[$loopIndex]['open']) - $data[$loopIndex]['low'];
                $bodySize = max($data[$loopIndex]['close'], $data[$loopIndex]['open']) - min($data[$loopIndex]['close'], $data[$loopIndex]['open']);


                if ($wickSize > $bodySize) {
                    $top = min($data[$loopIndex]['close'], $data[$loopIndex]['open']);
                } else if ($wickSize <= $bodySize) {
                    $top = max($data[$loopIndex]['close'], $data[$loopIndex]['open']);
                }


                $zoneSizePercent = CommonHelpers::getPercentDiff($data[$loopIndex]['low'], $top);

                if ($zoneSizePercent >= 2) {
                    $loopIndex--;

                    continue;
                }


                $allLevels[] = [
                    'type' => 'demand',
                    'top' =>  $top,
                    'bottom' => $data[$loopIndex]['low'],
                    'timestamp_initial' => $data[$loopIndex]['binance_timestamp'],
                    'timestamp_confirmed' => $data[$loopIndex + 4]['binance_timestamp'],
                ];
            }
            $loopIndex--;
        }






        // Filter these zones wrt 4h zones

        if ($filterOnHigher) {

            $allLevels4h = self::findZonesOn4h($data4hRaw, $candle15m);
            $filteredLevels1h = [];

            foreach ($allLevels as $level1h) {

                foreach ($allLevels4h as $level4h) {

                    $endTime = $level4h['timestamp_confirmed'];
                    $startTime =  $level4h['timestamp_initial'] - ($level4h['timestamp_confirmed'] - $level4h['timestamp_initial']);


                    if (
                        $level1h['timestamp_initial'] >= $startTime
                        && $level1h['timestamp_initial'] <= $endTime
                        && $level1h['top'] <= $level4h['top']
                        && $level1h['bottom'] >= $level4h['bottom']

                    ) {
                        $filteredLevels1h[] = $level1h;
                        break;
                    }
                }
            }
        } else {
            return $allLevels;
        }

        return $filteredLevels1h;
    }
    public static function findZonesOn4h($data4hRaw, $candle15m)
    {

        $data = CommonHelpers::filterCandlestickData($data4hRaw, null, $candle15m['binance_timestamp']);
        $index = count($data) - 2;

        // Get All 4h Levels in timestamp ASC order
        $allLevels = [];


        $loopIndex = $index - 4;
        $demandZone = 0;
        $supplyCount = 0;
        while ($loopIndex > 4) {
            $pivot = CommonHelpers::checkPivot($data, $loopIndex, 4);



            if ($pivot === 'high_pivot') {


                $bottom = max($data[$loopIndex]['body_max'], $data[$loopIndex - 1]['body_max'], $data[$loopIndex + 1]['body_max']);


                $allLevels[] = [
                    'type' => 'supply',
                    'top' => $data[$loopIndex]['high'],
                    'bottom' =>  $bottom,
                    'timestamp_initial' => $data[$loopIndex]['binance_timestamp'],
                    'timestamp_confirmed' => $data[$loopIndex + 4]['binance_timestamp'],
                ];
            }

            if ($pivot === 'low_pivot') {

                $top = min($data[$loopIndex]['body_min'], $data[$loopIndex - 1]['body_min'], $data[$loopIndex + 1]['body_min']);;


                $allLevels[] = [
                    'type' => 'demand',
                    'top' =>  $top,
                    'bottom' => $data[$loopIndex]['low'],
                    'timestamp_initial' => $data[$loopIndex]['binance_timestamp'],
                    'timestamp_confirmed' => $data[$loopIndex + 4]['binance_timestamp'],
                ];
            }
            $loopIndex--;
        }

        return $allLevels;
    }
}
