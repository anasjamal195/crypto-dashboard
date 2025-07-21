<?php

namespace App\Services;

use App\CommonHelpers;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpeningConditionServiceLive
{
    public static $activeExchange;
    public $account;
    public $workerId;

    public static $candlesToCheck = 1000;
    public static $volumeMA5ValidFor = 1000;
    public static $upperWickValidFor = 1000;
    public static $bollSqueezValidFor = 1000;
    public static $lowPivots = [];
    public static $highPivots = [];


    public function __construct($workerId, $account, $activeExchange)
    {
        $this->workerId = $workerId;
        $this->account = $account;
        self::$activeExchange = $activeExchange;
    }


    public static function getOpeningOn15m($symbol)
    {

        $interval = '15m';
        $cacheKey = "last_checked_for_opening_{$symbol}_{$interval}";

        if (Cache::get($cacheKey, 0)) {
            return [
                'direction' => null,
                'formula' => 'Pivot Swing'
            ];
        }


        $data = self::$activeExchange === 'binance' ?
            BinanceApiService::getCandleStickDataExternal($symbol, $interval, 500, null,  'FUTURE')
            : HyperLiquidApiService::getCandleStickDataExternal($symbol, $interval, 500, null, 'FUTURE');


        $index = count($data) - 2;



        $cacheValue = time() * 1000;

        $now = now();
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $minutesToNextRounded = intval(($intervalToMins - ($now->minute % $intervalToMins)) / 2);
        $nextRoundedTime = $now->copy()->addMinutes($minutesToNextRounded)->startOfMinute();


        Cache::put($cacheKey, $cacheValue, $nextRoundedTime);

        // Check candle closing
        if (!CommonHelpers::checkCandleClosing($data, 300)) {
            return [
                'direction' => null,
                'formula' => 'Pivot Swing - 15m'
            ];
        }

        // LONG ENTRY
        if (
            self::checkConditionSetLong15m($symbol, $data, $index) === 'LONG'
        ) {
            return [
                'direction' => 'LONG',
                'formula' => 'Pivot Swing - 15m'
            ];
        }


        // SHORT Entry (Disabled for now)
        // if (
        //     self::checkConditionSetShort15m($symbol, $data, $index) === 'SHORT'
        // ) {
        //     return [
        //         'direction' => 'SHORT',
        //         'formula' => 'Pivot Swing - 15m'
        //     ];
        // }



        return [
            'direction' => null,
            'formula' => 'Pivot Swing - 15m'
        ];
    }






    public static function checkConditionSetLong15m($symbol, $data, $index)
    {

        $interval = '15m';


        return 'LONG';
        for ($i = 10; $i <= ($index - 6); $i++) {

            $p = CommonHelpers::checkPivot($data, $i, 6);

            if ($p === 'low_pivot') {
                self::$lowPivots[] = $i;
            }
        }


        $pivot = CommonHelpers::checkPivot($data, $index - 6, 6);


        $lastPivotIndex = count(self::$lowPivots) - 1;

        if (

            $pivot === 'low_pivot'
            && count(self::$lowPivots) > 3
            && $data[self::$lowPivots[$lastPivotIndex]]['low'] > $data[self::$lowPivots[$lastPivotIndex - 1]]['low']
            && $data[self::$lowPivots[$lastPivotIndex - 1]]['low'] < $data[self::$lowPivots[$lastPivotIndex - 2]]['low']
            && $data[self::$lowPivots[$lastPivotIndex - 2]]['low'] < $data[self::$lowPivots[$lastPivotIndex - 3]]['low']

            // && $data[self::$lowPivots[$lastPivotIndex - 1]]['volume'] < $data[self::$lowPivots[$lastPivotIndex - 2]]['volume']

        ) {

            return 'LONG';
        }


        return null;
    }










    public static function checkConditionSetShort15m($symbol, $data, $index)
    {


        $interval = '15m';
        for ($i = 10; $i <= ($index - 6); $i++) {

            $p = CommonHelpers::checkPivot($data, $i, 6);

            if ($p === 'high_pivot') {
                self::$highPivots[] = $i;
            }
        }


        $pivot = CommonHelpers::checkPivot($data, $index - 6, 6);






        $lastPivotIndexHigh = count(self::$highPivots) - 1;

        if (

            $pivot === 'high_pivot'
            && count(self::$highPivots) > 3
            && $data[self::$highPivots[$lastPivotIndexHigh]]['high'] < $data[self::$highPivots[$lastPivotIndexHigh - 1]]['high']
            && $data[self::$highPivots[$lastPivotIndexHigh - 1]]['high'] > $data[self::$highPivots[$lastPivotIndexHigh - 2]]['high']
            && $data[self::$highPivots[$lastPivotIndexHigh - 2]]['high'] > $data[self::$highPivots[$lastPivotIndexHigh - 3]]['high']

            && $data[self::$highPivots[$lastPivotIndexHigh - 1]]['volume'] < $data[self::$highPivots[$lastPivotIndexHigh - 1]]['volumeMA5']


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












    // ######################### MISC Functions #################################

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
            'formula' => 'Live Trades',
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
    public static function checkConfirmTradeValidity($symbol, $type, $data, $index, $interval, $intention = null)
    {
        $ictId = self::getIctId($symbol, $type, $intention);
        if (
            !$ictId
        ) {
            return null;
        }



        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->first();
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->checkpoint_timestamp, $interval);

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

        DB::table('confirmed_trades')->where('coin_name', $symbol)->where('type', $type)->orderBy('update_time', 'DESC')->delete();
        // $entry = DB::table('confirmed_trades')->where('coin_name', $symbol)->where('type', $type)->orderBy('update_time', 'DESC')->update(
        //     [
        //         'trade_confirmed' => 1,
        //         'type' => $newType,
        //         'openingTimestamp' => $newType != 'TBD' ? $data[$index]['binance_timestamp'] : null,
        //     ]
        // );
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
}
