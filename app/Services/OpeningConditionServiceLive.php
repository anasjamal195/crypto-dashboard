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
    public static $lowPivotsA = [];
    public static $highPivotsA = [];

    public static $lowPivotsB = [];
    public static $highPivotsB = [];

    public static $formulaACoins = [
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


        // Major Altcoins
        'AVAXUSDT',
        'DOTUSDT',
        'TRXUSDT',
        // 'SHIBUSDT',
        'XRPUSDT',

        // DeFi/Layer 1 Tokens
        'FTMUSDT',
        'ONEUSDT',
        'EGLDUSDT',
        'ZILUSDT',
        'WAVESUSDT',

        // Gaming/Metaverse
        'ENJUSDT',
        'CHZUSDT',
        'GALAUSDT',

        // Established Altcoins
        'XLMUSDT',
        'EOSUSDT',
        'ETCUSDT',
        'BCHUSDT',

        // Mid-caps with good patterns
        'CRVUSDT',
        'COMPUSDT',
        'MKRUSDT',
        'YFIUSDT'
    ];

    public static $formulaBCoins = [
        'BNBUSDT',      // Binance ecosystem - appeared in all tests
        'AVAXUSDT',     // Layer 1 - appeared in all tests  
        'VETUSDT',      // Supply chain - appeared in all tests
        'LTCUSDT',      // Established alt - appeared in 3 tests
        'SANDUSDT',     // Gaming/Metaverse - appeared in 3 tests
        'ADAUSDT',      // Major Layer 1 - appeared in 3 tests
        'MKRUSDT',      // DeFi governance - appeared in 3 tests
        'COMPUSDT',     // DeFi lending - appeared in 3 tests


        // TIER 2: STRONG CANDIDATES (appeared in 2+ tests)
        'SOLUSDT',      // Major Layer 1
        'ATOMUSDT',     // Cosmos ecosystem
        'NEARUSDT',     // Layer 1 protocol
        'DOGEUSDT',     // High volume meme coin
        'AAVEUSDT',     // DeFi lending
        'FILUSDT',      // Decentralized storage
        'ETCUSDT',      // Ethereum Classic
        'CHZUSDT',      // Sports tokens
        'ICPUSDT',      // Internet Computer
        'EGLDUSDT',     // MultiversX
        'XRPUSDT',      // Payment token
        'TRXUSDT',      // Established blockchain
        'UNIUSDT',      // Leading DEX
        'BCHUSDT',      // Bitcoin fork
        'CRVUSDT',      // DeFi yield farming
        'ALGOUSDT',     // Pure proof-of-stake
        'MANAUSDT',     // Metaverse
        'GALAUSDT',     // Gaming


        // TIER 3: ADDITIONAL HIGH-POTENTIAL COINS
        // Based on similar characteristics

        // Layer 1 & Infrastructure (similar to AVAX, SOL, ADA patterns)
        'DOTUSDT',      // Polkadot - Multi-chain protocol
        'MATICUSDT',    // Polygon - Ethereum scaling
        'FTMUSDT',      // Fantom - High-speed blockchain
        'HBARUSDT',     // Hedera - Enterprise blockchain
        'FLOWUSDT',     // Flow - NFT-focused blockchain
        'APTUSDT',      // Aptos - High-performance L1
        'SUIUSDT',      // Sui - Next-gen L1
        'SEIUSDT',      // Sei - Trading-focused L1

        // DeFi Tokens (similar to AAVE, UNI, CRV patterns)
        'LINKUSDT',     // Chainlink - Oracle network
        'SUSHIUSDT',    // SushiSwap - DEX
        'CAKEUSDT',     // PancakeSwap - BSC DEX
        '1INCHUSDT',    // 1inch - DEX aggregator
        'SNXUSDT',      // Synthetix - Synthetic assets
        'GMXUSDT',      // GMX - Perpetual trading
        'RDNTUSDT',     // Radiant - Cross-chain lending
        'PEPEUSDT',     // High volume meme token

        // // Gaming/Metaverse (similar to SAND, MANA, GALA patterns)
        'AXSUSDT',      // Axie Infinity - P2E gaming
        'ENJUSDT',      // Enjin - Gaming platform
        'IMXUSDT',      // Immutable X - Gaming L2
        'BEAMUSDT',     // Beam - Gaming blockchain
        'RONINUSDT',    // Ronin - Gaming sidechain
        'MAGICUSDT'    // Magic - Gaming ecosystem
    ];



    public static $formulaType = null;

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

        // ############################################### Formula A Entries ###############################################

        if (in_array($symbol, self::$formulaACoins)) {

            if (
                self::checkConditionSetLong15mA($symbol, $data, $index) === 'LONG'
            ) {

                $sl = $index;
                $sl = self::$lowPivotsA[count(self::$lowPivotsA) - 2];
                $loopIndex = count(self::$lowPivotsA) - 1;
                while ($loopIndex >= 0 && $data[self::$lowPivotsA[$loopIndex]]['low'] >= $data[$index]['close']) {
                    $sl = self::$lowPivotsA[$loopIndex];
                    $loopIndex--;
                }
                if ($data[$sl]['low'] >= $data[$index]['close']) {
                    $stopLoss = 2;
                } else {
                    $stopLoss = CommonHelpers::getPercentDiff($data[$index]['close'], $data[$sl]['low']) + 0.7;
                    if ($stopLoss >= 3) {
                        Log::info('TriggersThreadOrderBook: Canceled Opening due to SL ' . $stopLoss . ' Formula A ' . $symbol);

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
                    'formula' => 'Pivot Sweep - 15m - A',
                    'profitIncrementPercentage' => $profitIncPer,
                    'stopLoss' => $stopLoss,
                    'targetProfit' => $targetProfit,
                ];
            }
        }





        // ############################################### Formula B Entries ###############################################

        if (in_array($symbol, self::$formulaBCoins)) {

            if (
                self::checkConditionSetLong15mB($symbol, $data, $index) === 'LONG'
            ) {

                $sl = $index;
                $sl = self::$lowPivotsB[count(self::$lowPivotsB) - 2];
                $loopIndex = count(self::$lowPivotsB) - 1;
                while ($loopIndex >= 0 && $data[self::$lowPivotsB[$loopIndex]]['low'] >= $data[$index]['close']) {
                    $sl = self::$lowPivotsB[$loopIndex];
                    $loopIndex--;
                }
                if ($data[$sl]['low'] >= $data[$index]['close']) {
                    $stopLoss = 2;
                } else {
                    $stopLoss = CommonHelpers::getPercentDiff($data[$index]['close'], $data[$sl]['low']) + 0.7;
                    if ($stopLoss >= 3) {
                        Log::info('TriggersThreadOrderBook: Canceled Opening due to SL ' . $stopLoss . ' Formula B ' . $symbol);

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
                    'formula' => 'Pivot Sweep - 15m - B',
                    'profitIncrementPercentage' => $profitIncPer,
                    'stopLoss' => $stopLoss,
                    'targetProfit' => $targetProfit,
                ];
            }
        }


        return [
            'direction' => null,
            'formula' => 'Pivot Sweep - 15m - Not Found',
            'profitIncrementPercentage' => $profitIncPer,
            'stopLoss' => $stopLoss,
            'targetProfit' => $targetProfit,
        ];
    }

    public static function  checkConditionSetLong15mA($symbol, $data, $index)
    {

        $interval = '15m';

        self::$lowPivotsA = [];
        for ($i = 10; $i <= ($index - 6); $i++) {
            $p = CommonHelpers::checkPivot($data, $i, 6);
            if ($p === 'low_pivot') {
                self::$lowPivotsA[] = $i;
            }
        }

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
            Log::info('TriggersThreadOrderBook: Going to open with Formula A ' . $symbol);

            return 'LONG';
        }
    }






    public static function  checkConditionSetLong15mB($symbol, $data, $index)
    {

        $interval = '15m';

        self::$lowPivotsB = [];
        for ($i = 10; $i <= ($index - 2); $i++) {
            $p = CommonHelpers::checkPivot($data, $i, 2);
            if ($p === 'low_pivot') {
                self::$lowPivotsB[] = $i;
            }
        }

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
            Log::info('TriggersThreadOrderBook: Going to open with Formula B ' . $symbol);
            return 'LONG';
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
