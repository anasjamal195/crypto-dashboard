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

        $targetProfit = 0.7;
        $stopLoss = 2;
        $profitIncPer = 0.2;

        if (Cache::get($cacheKey, 0)) {
            return [
                'direction' => null,
                'formula' => 'Pivot Sweep',
                'profitIncrementPercentage' => $profitIncPer,
                'stopLoss' => $stopLoss,
                'targetProfit' => $targetProfit,
            ];
        }




        // ########### Checking Candle closing on specific interval ##############
        if (!CommonHelpers::checkCandleClosingAbsolute($interval, 60)) {
            return [
                'direction' => null,
                'formula' => 'Pivot Sweep - 15m',
                'profitIncrementPercentage' => $profitIncPer,
                'stopLoss' => $stopLoss,
                'targetProfit' => $targetProfit,
            ];
        }


        $data =
            // self::$activeExchange === 'binance' ?
            BinanceApiService::getCandleStickData($symbol, $interval, 500, null,  'FUTURE');
        // : HyperLiquidApiService::getCandleStickData($symbol, $interval, 500, $timestampTest, 'FUTURE');



        $index = count($data) - 2;

        // dd($data[$index]);
        $cacheValue = time() * 1000;

        Cache::put($cacheKey, $cacheValue, now()->addSeconds(15));









        // LONG ENTRY
        if (
            self::checkConditionSetLong15m($symbol, $data, $index) === 'LONG'
        ) {

            $sl = self::$lowPivots[count(self::$lowPivots) - 2];

            $loopIndex = count(self::$lowPivots) - 1;

            while ($loopIndex >= 0 && $data[self::$lowPivots[$loopIndex]]['low'] >= $data[$index]['close']) {
                $sl = self::$lowPivots[$loopIndex];
                $loopIndex--;
            }


            if ($data[$sl]['low'] >= $data[$index]['close']) {
                $stopLoss = 2;
            } else {
                $stopLoss = CommonHelpers::getPercentDiff($data[$index]['close'], $data[$sl]['low']) + 0.7;


                if ($stopLoss >= 5) {
                    return [
                        'direction' => null,
                        'formula' => 'Pivot Sweep - 15m',
                        'profitIncrementPercentage' => $profitIncPer,
                        'stopLoss' => $stopLoss,
                        'targetProfit' => $targetProfit,
                    ];
                }
            }

            return [
                'direction' => 'LONG',
                'formula' => 'Pivot Sweep - 15m',
                'profitIncrementPercentage' => $profitIncPer,
                'stopLoss' => $stopLoss,
                'targetProfit' => $targetProfit,
            ];
        }


        // SHORT Entry (Disabled for now)
        // if (
        //     self::checkConditionSetShort15m($symbol, $data, $index) === 'SHORT'
        // ) {

        //     $sl = self::$highPivots[count(self::$highPivots) - 2];
        //     $slPercentage = CommonHelpers::getPercentDiff($data[$index]['close'], $data[$sl]['high']);
        //     $atrPercentage = round(($data[$index]['atr14']  / $data[$index]['close']) * 100, 3);
        //     if ($slPercentage > 3 && $atrPercentage < 0.4) {
        //         $sl = self::$highPivots[count(self::$highPivots) - 1];
        //     }
        //     $bufferSize = 0.5 * $data[$index]['atr14'];
        //     $profitIncPer = $bufferSize;

        //     $stopLoss = CommonHelpers::getPercentDiff($data[count($data) - 1]['close'], $data[$sl]['high']);

        //     return [
        //         'direction' => 'SHORT',
        //         'formula' => 'Pivot Sweep - 15m',
        //         'profitIncrementPercentage' => $profitIncPer,
        //         'stopLoss' => $stopLoss,
        //         'targetProfit' => $targetProfit,
        //     ];
        // }

        return [
            'direction' => null,
            'formula' => 'Pivot Sweep - 15m - Not Found',
            'profitIncrementPercentage' => $profitIncPer,
            'stopLoss' => $stopLoss,
            'targetProfit' => $targetProfit,
        ];
    }



    public static function getOpeningOn15mReconcile($symbol, $timestampTrade)
    {

        $interval = '15m';
        $cacheKey = "last_checked_for_opening_{$symbol}_{$interval}";

        // if (Cache::get($cacheKey, 0)) {
        //     return [
        //         'direction' => null,
        //         'formula' => 'Pivot Sweep'
        //     ];
        // }

        $timestampTest = $timestampTrade - (498 * CommonHelpers::$binanceIntervals[$interval] * 60 * 1000);

        $data =
            // self::$activeExchange === 'binance' ?
            BinanceApiService::getCandleStickData($symbol, $interval, 500, $timestampTest,  'FUTURE');
        // : HyperLiquidApiService::getCandleStickData($symbol, $interval, 500, $timestampTest, 'FUTURE');



        $index = count($data) - 2;

        // dd($data[$index]);
        $cacheValue = time() * 1000;

        $now = now();
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $minutesToNextRounded = intval(($intervalToMins - ($now->minute % $intervalToMins)) / 2);
        $nextRoundedTime = $now->copy()->addMinutes($minutesToNextRounded)->startOfMinute();

        // dd($data[$index],end($data));
        // Cache::put($cacheKey, $cacheValue, $nextRoundedTime);

        // Check candle closing
        // if (!CommonHelpers::checkCandleClosing($data, 120)) {
        //     return [
        //         'direction' => null,
        //         'formula' => 'Pivot Sweep - 15m'
        //     ];
        // }

        // LONG ENTRY
        if (
            self::checkConditionSetLong15m($symbol, $data, $index) === 'LONG'
        ) {
            return [
                'direction' => 'LONG',
                'formula' => 'Pivot Sweep - 15m'
            ];
        }


        // SHORT Entry (Disabled for now)
        if (
            self::checkConditionSetShort15m($symbol, $data, $index) === 'SHORT'
        ) {
            return [
                'direction' => 'SHORT',
                'formula' => 'Pivot Sweep - 15m'
            ];
        }

        return [
            'direction' => null,
            'formula' => 'Pivot Sweep - 15m - Not Found'
        ];
    }


    public static function checkConditionSetLong15m($symbol, $data, $index)
    {

        $interval = '15m';

        self::$lowPivots = [];
        for ($i = 10; $i <= ($index - 6); $i++) {
            $p = CommonHelpers::checkPivot($data, $i, 6);
            if ($p === 'low_pivot') {
                self::$lowPivots[] = $i;
            }
        }

        $initialSetup = false;



        $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, $interval, 'LONG');


        if (!$confirmedTrade) {



            $lastPivotIndex = count(self::$lowPivots) - 1;

            $checkPreviousCollision = true;
            for ($i = $lastPivotIndex; $i < $index - 2; $i++) {
                if (
                    count(self::$lowPivots) > 3
                    && $data[$i]['low'] <=  ($data[self::$lowPivots[$lastPivotIndex]]['low'] * (1 - 0.1 / 100))
                    && $data[$i]['close'] > ($data[self::$lowPivots[$lastPivotIndex]]['low'] * (1 + 0.05 / 100))
                ) {
                    $checkPreviousCollision = false;
                    break;
                }
            }
            if (
                count(self::$lowPivots) > 3
                && $data[$index]['low'] <=  ($data[self::$lowPivots[$lastPivotIndex]]['low'] * (1 - 0.1 / 100))
                && $data[$index]['close'] > ($data[self::$lowPivots[$lastPivotIndex]]['low'] * (1 + 0.05 / 100))
                && $checkPreviousCollision
                && $data[self::$lowPivots[$lastPivotIndex]]['low'] <= $data[self::$lowPivots[$lastPivotIndex]]['bb_lower']


            ) {
                Log::info('TriggersThreadOrderBook ' . $symbol . " LONG Opening Conditions Detail: ");
                Log::info("1) Pivots Timestamps:  " . implode(' ', [
                    $data[self::$lowPivots[$lastPivotIndex]]['timestampReadable'],
                ]));
                Log::info("2) LONG Current Time: " . $data[$index]['timestampReadable']);
                if ($data[$index]['per'] > 0.1) {
                    Log::info("3) LONG Opening Right Away:! ");
                    return 'LONG';
                } else {
                    Log::info("3) LONG Opening Delayed! ");

                    $initialSetup = true;
                }
            }
        }

        $steps = [
            [
                'condition' => (
                    $initialSetup
                ),
                'candlesToCheck' => 30,
            ],
            [
                'condition' => (
                    $data[$index]['per'] > 0.1
                ),
                'candlesToCheck' => 10,
            ],
        ];

        // Process steps sequentially
        foreach ($steps as $stepIndex => $step) {



            if (!$step['condition']) {
                continue;
            }


            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, $interval, 'LONG');

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
                    $reconcile = self::checkPreviousTriggerBullish($data, $index, $interval, $confirmedTrade);

                    if (
                        $reconcile['verifiedIndex'] == $index
                    )
                        return 'LONG';
                }
            }
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

        $lastPivotIndexHigh = count(self::$highPivots) - 1;
        $initialSetupShort = false;
        if (

            count(self::$highPivots) > 3
            && $data[$index]['high'] >=  ($data[self::$highPivots[$lastPivotIndexHigh]]['high'] * (1 + 0.05 / 100))
            && $data[$index]['close'] < ($data[self::$highPivots[$lastPivotIndexHigh]]['high'] * (1 - 0.05 / 100))
            // && $data[$index]['open'] < ($data[self::$highPivots[$lastPivotIndexHigh]]['high'] * (1 - 0.05 / 100))
            && $data[$index]['histogram'] < $data[$index - 1]['histogram']

        ) {

            Log::info('TriggersThreadOrderBook ' . $symbol . " SHORT Opening Conditions Detail: ");
            Log::info("1) Pivots Timestamps:  " . implode(' ', [
                $data[self::$highPivots[$lastPivotIndexHigh]]['timestampReadable'],
            ]));
            Log::info("2) SHORT Current Time: " . $data[$index]['timestampReadable']);
            if ($data[$index]['per'] < 0) {

                Log::info("3) SHORT Opening Right Away:! ");
                return 'SHORT';
            } else {
                Log::info("3) SHORT Opening Delayed:! ");

                $initialSetupShort = true;
            }
        }

        $steps = [
            [
                'condition' => (
                    $initialSetupShort
                ),
                'candlesToCheck' => 20,
            ],
            [
                'condition' => (
                    $data[$index]['per'] < -0.1
                ),
                'candlesToCheck' => 10,
            ],


        ];

        // Process steps sequentially
        foreach ($steps as $stepIndex => $step) {


            if (!$step['condition']) {
                continue;
            }


            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, $interval, 'SHORT');

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
                    if (
                        true
                    )
                        return 'SHORT';
                }
            }
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
    public static function checkPreviousTriggerBullish($data, $index, $interval, $confirmedTrade)
    {
        $ctIndex = self::getIndexDiffFromTimestamps($confirmedTrade->confirm_candle_timestamp, $data[$index]['binance_timestamp'], $interval, true);
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
