<?php

namespace App\Services\InternalTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\MarketTrendService;
use App\Services\SupportResistanceAnalyzer;
use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ReportServiceImproved
{

    // Essential Properties
    public static $delayMs = 10;
    public static $interval = '15m';
    public static $targetProfit = 0.8;
    public static $stopLoss = 0.6;
    public static $stopLossWaitingDuration = 0;
    public static $longEnabled = true;
    public static $shortEnabled = true;
    public static $earlyClosingEnabled = true;

    // Trend Analysis
    public static $trendReferenceSymbol = 'BTCUSDT';
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

    // Backtest & Formula
    public static $backTestTimeUnix;
    public static $formula;
    public static $baseReportFormula;
    public static $isBaseReport = true;
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

    // Dynamic TP/SL
    public static $dynamicTP = 0;
    public static $dynamicSL = 0;
    public static $dynamicTPSLgap = 0.5;
    public static $initialTpPercent = 0.8;
    public static $initialSlPercent = 0.6;
    public static $supportResistanceCandleSpan = 12;

    // Limits for candle data
    public static $limit = 1000;
    public static $initialWaitingCandles = 100;

    // Cooldown & Safety
    public static $srLossCooldown = [];
    public static $consecutiveLosses = [];
    public static $consecutiveLossTimestamp = [];
    public static $btc4hCached = [];
    public static $btc4hEndCached = 0;
    public static $btcBullTrendCached = null;
    public static $btcBearTrendCached = null;

    // Progression Tracking
    public static $progressionDetailsLONG = [];
    public static $progressionDetailsSHORT = [];
    public static $progressionDetailsLONGMACD = [];
    public static $progressionDetailsLONGSR = [];
    public static $progressionDetailsLONGFVG = [];
    public static $progressionDetailsSHORTMACD = [];
    public static $progressionDetailsSHORTSR = [];
    public static $progressionDetailsSHORTFVG = [];
    public static $timeWiseTradesCount = [];

    // Coins Config
    public static $formulaACoins = [
        'BTCUSDT',
        'ETHUSDT',
        'SOLUSDT'
    ];

    public static $formulaBCoins = [
        'BTCUSDT',
        'ETHUSDT',
        'SOLUSDT'
    ];

    public static function generateCoinReport(
        $cmd = null,
        $formula = 'Default',
        $timestamp = null,
        $baseReportFormula = '',
        $baseReport = true
    ) {

        $coins = array_values(array_unique(array_merge(self::$formulaACoins, self::$formulaBCoins)));

        self::$formula = $formula;
        self::$backTestTimeUnix = $timestamp;
        self::$baseReportFormula = $baseReportFormula;
        self::$isBaseReport = $baseReport;
        self::$coinLimit = count($coins);
        self::$srLossCooldown = [];
        self::$consecutiveLosses = [];
        self::$consecutiveLossTimestamp = [];

        system('clear');
        $cmd->info('Processing: 0 %');
        self::addFormulaDetails();
        DB::table('confirmed_trades')->truncate();

        foreach ($coins as $index => $coin) {

            try {
                $symbol = $coin;
                $data = null;
                $maxRetries = 3;

                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    try {
                        $data = BinanceApiService::getCandleStickDataExtended($symbol, self::$interval, self::$limit, self::$backTestTimeUnix, 'FUTURE');
                        if (!empty($data) && is_array($data) && !is_null($data[0]['binance_timestamp'] ?? null)) {
                            break;
                        }
                        $cmd->warn("Attempt {$attempt}/{$maxRetries}: Empty data for {$symbol}, retrying...");
                        CommonHelpers::delayMS(2000);
                    } catch (\Exception $retryEx) {
                        if ($attempt === $maxRetries) throw $retryEx;
                        $cmd->warn("Attempt {$attempt}/{$maxRetries}: API failed for {$symbol} ({$retryEx->getMessage()}), retrying...");
                        CommonHelpers::delayMS(2000);
                    }
                }

                self::$trades = self::processCandles($symbol, $data);
                DB::table('coin_reports')->where('symbol', $symbol)->where('interval', self::$interval)->where('formula', self::$formula)->where('market', 'FUTURE')->delete();

                foreach (self::$trades as $trade) {
                    DB::table('coin_reports')->insert($trade);
                    CommonHelpers::delayMS(5);
                }

                $perProgress = (($index + 1) / count($coins)) * 100;
                $cmd->info('Processing: ' . round($perProgress) . ' % (' . count(self::$trades) . ' trades for ' . $symbol . ')');
                DB::table('formula_details')->where('formula', self::$formula)->update([
                    'progress' => $perProgress,
                ]);
            } catch (\Exception $e) {
                $cmd->error('Error Occured for ' . $coin . ': ' . $e->getMessage());
                Log::error("Failed to update coin reports for {$coin}: " . $e->getMessage());
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
        self::$dynamicTP = 0;
        self::$dynamicSL = 0;
        self::$btc4hCached = [];
        self::$btc4hEndCached = 0;
        self::$btcBullTrendCached = null;
        self::$btcBearTrendCached = null;

        $lastTs = $data[count($data) - 1]['binance_timestamp'];

        $data4hRaw = self::fetchCandlesWithRetry($symbol, '4h', 1000, $lastTs);
        $data1hRaw = self::fetchCandlesWithRetry($symbol, '1h', 1000, $lastTs);
        self::$btc4hCached = self::fetchCandlesWithRetry('BTCUSDT', '4h', 100, $lastTs);

        foreach ($data as $index => $candle) {

            if ($index < self::$initialWaitingCandles) {
                continue;
            }

            if (self::$waitingCandles) {
                self::$waitingCandles--;
                continue;
            }

            self::setCurrentFVG($data, $data1hRaw, $index);
            self::setSRLevels($data, $data4hRaw, $index);

            $tagName = null;

            if (self::$open_price == 0) {
                self::$tradeType = self::handleOpeningConditions($symbol, $data, $index, $tagName);

                if (self::$tradeType) {
                    $key = $symbol . '_' . self::$tradeType;
                    if ((self::$consecutiveLosses[$key] ?? 0) >= 3) {
                        $candlesSince = ($data[$index]['binance_timestamp'] - (self::$consecutiveLossTimestamp[$key] ?? 0)) / (15 * 60 * 1000);
                        if ($candlesSince < 48) {
                            self::resetTradeParams();
                            continue;
                        }
                    }

                    if (self::$dynamicTP == 0) {
                        $atrPercent = self::getATRPercent($data, $index);
                        $slPct = max(0.6, min(2.0, $atrPercent * 0.8));
                        $tpPct = max(0.8, min(3.0, $atrPercent * 1.2));
                        self::$dynamicTPSLgap = max(0.5, min(1.5, $atrPercent * 2.0));
                        if (self::$tradeType === 'LONG') {
                            self::$dynamicTP = $data[$index]['close'] * (1 + $tpPct / 100);
                            self::$dynamicSL = $data[$index]['close'] * (1 - $slPct / 100);
                        } else {
                            self::$dynamicTP = $data[$index]['close'] * (1 - $tpPct / 100);
                            self::$dynamicSL = $data[$index]['close'] * (1 + $slPct / 100);
                        }
                    }

                    $candle['formulaType'] = self::$formulaType;
                    $candle['dynamicTP'] = self::$dynamicTP;
                    $candle['dynamicSL'] = self::$dynamicSL;

                    if (self::$currentZoneStatus) {
                        if (self::$currentZoneStatus['zoneType'] === 'support')
                            $candle['latestBearOb'] = self::$currentZoneStatus['currentZone'];
                        if (self::$currentZoneStatus['zoneType'] === 'resistance')
                            $candle['latestBullOb'] = self::$currentZoneStatus['currentZone'];
                    }

                    $supportResistance = self::getSupportResistance($data, $index);
                    $candle['currentSupport'] = $supportResistance['support'];
                    $candle['currentResistance'] = $supportResistance['resistance'];
                    $candle['tagName'] = $tagName;

                    self::$currentTrade['buyingCandle'] = json_encode($candle);
                    self::$currentTrade['previousCandle'] = json_encode($data[$index - 1]);
                    self::$currentTrade['openingTimestamp'] = $data[$index]['binance_timestamp'];

                    self::$open_price = $candle['close'];
                    self::$extremePrice = self::$open_price;
                    self::$openingIndex = $index;
                }
            } else {
                $closingPrice = self::handleClosingConditions($symbol, $data, $index);

                if (self::$tradeType === 'SHORT') {
                    self::$extremePrice = max(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'high'));
                }
                if (self::$tradeType === 'LONG') {
                    self::$extremePrice = min(array_column(array_slice($data, self::$openingIndex, $index - self::$openingIndex + 1), 'low'));
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
                    self::$currentTrade['tagName'] = $tagName;
                    self::$currentTrade['duration'] = ($data[$index]['binance_timestamp'] - $data[self::$openingIndex]['binance_timestamp']) / (1000 * 60);
                    self::$trades[] = self::$currentTrade;

                    error_log(self::$tradeType . " Entry " . self::$formulaType . " for {$symbol}: " . self::$currentTrade['profit']);

                    $safetyKey = $symbol . '_' . self::$tradeType;
                    if ($profit <= 0) {
                        self::$consecutiveLosses[$safetyKey] = (self::$consecutiveLosses[$safetyKey] ?? 0) + 1;
                        self::$consecutiveLossTimestamp[$safetyKey] = $data[$index]['binance_timestamp'];
                        if (strpos(self::$formulaType, 'SR_') !== false) {
                            self::$srLossCooldown[$safetyKey] = $data[$index]['binance_timestamp'];
                        }
                    } else {
                        self::$consecutiveLosses[$safetyKey] = 0;
                    }

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
    //                               Opening Logic
    // ###############################################################################

    public static function handleOpeningConditions($symbol, $data, $index, &$tagName)
    {
        $btc4hSlice = CommonHelpers::filterCandlestickData(self::$btc4hCached, null, $data[$index]['binance_timestamp']);
        $btcEnd4h = count($btc4hSlice) - 2;
        $btcStart4h = max(0, $btcEnd4h - 11);
        $macdBullCount = 0;
        for ($i = $btcStart4h; $i <= $btcEnd4h; $i++) {
            if ($btc4hSlice[$i]['dif'] > $btc4hSlice[$i]['dea']) $macdBullCount++;
        }
        $btcBullTrend = $macdBullCount >= 8;
        $btcBearTrend = $macdBullCount <= 3;

        // LONG - Try MACD first, then S/R
        if (self::$longEnabled && !$btcBearTrend) {
            $entry = self::checkConditionSetLongMACD($symbol, $data, $index);
            if ($entry === 'LONG') {
                $tagName = 'MACD';
                self::$formulaType = 'MACD_LONG';
                return 'LONG';
            }

            $entry = self::checkConditionSetLongSR($symbol, $data, $index);
            if ($entry === 'LONG') {
                $tagName = 'SR';
                self::$formulaType = 'SR_LONG';
                return 'LONG';
            }
        }

        // SHORT - Try MACD first, then S/R
        if (self::$shortEnabled && !$btcBullTrend) {
            $entry = self::checkConditionSetShortMACD($symbol, $data, $index);
            if ($entry === 'SHORT') {
                $tagName = 'MACD';
                self::$formulaType = 'MACD_SHORT';
                return 'SHORT';
            }

            $entry = self::checkConditionSetShortSR($symbol, $data, $index);
            if ($entry === 'SHORT') {
                $tagName = 'SR';
                self::$formulaType = 'SR_SHORT';
                return 'SHORT';
            }
        }

        return null;
    }

    // ###############################################################################
    //                     MACD Multi-Step Entry (LONG)
    // ###############################################################################

    public static function checkConditionSetLongMACD($symbol, $data, $index)
    {
        if (!self::$longEnabled) {
            return null;
        }

        if (!self::$isBaseReport) {
            $currentAccuracy = self::parseAccuracy(self::$progressionDetailsLONGMACD, $data[$index]['binance_timestamp'], 6);
            if ($currentAccuracy != -1 && $currentAccuracy < 73) {
                return null;
            }
        }

        $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

        $steps = [
            [
                'condition' => (
                    $data[$index]['volume'] >= (1.2 * CommonHelpers::getSMAAtIndex($data, $index, 20, 'volume'))
                ),
                'candlesToCheck' => 10,
            ],
            [
                'condition' => (
                    $data[$index]['close'] >= $data[$index]['bb_middle']
                    && $data[$index]['rsi6'] > 45
                    && $bbAnalysis['is_expanding']
                ),
                'candlesToCheck' => 10
            ],
            [
                'condition' => (
                    $bbAnalysis['price_action']['is_near_lower_band']
                    && $data[$index]['rsi6'] >= 25
                    && $data[$index]['rsi6'] <= 45
                    && $data[$index]['volume'] >= $data[$index]['volumeMA5']
                ),
                'candlesToCheck' => 20
            ],
            [
                'condition' => (
                    ($bbAnalysis['price_action']['is_near_lower_band'] || $bbAnalysis['price_action']['crossed_lower_band'])
                    && $data[$index]['rsi6'] <= 20
                    && $data[$index]['volume'] >= (1.5 * $data[$index]['volumeMA10'])
                ),
                'candlesToCheck' => 20
            ],
            [
                'condition' => (
                    $data[$index]['per'] > 0
                    && $data[$index]['low'] < $data[$index]['bb_lower']
                ),
                'candlesToCheck' => 10,
            ],
        ];

        foreach ($steps as $stepIndex => $step) {

            if (!$step['condition']) {
                continue;
            }

            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, 'LONG');

            if ($stepIndex === 0) {
                if (!$confirmedTrade) {
                    self::insertConfirmBasicTradeEntry($symbol, 'TBD', $data, $index, 'LONG', $step['candlesToCheck']);
                }
                continue;
            }

            $requiredCheckpoint = $stepIndex - 1;

            if ($confirmedTrade && $confirmedTrade->checkpoints === $requiredCheckpoint) {
                self::updateConfirmTradeCheckpoint($symbol, 'TBD', $data, $index, 'LONG', $step['candlesToCheck']);

                $isFinal = $stepIndex === count($steps) - 1;

                if ($isFinal) {
                    self::confirmOpening($symbol, 'TBD', $data, $index, 'LONG');

                    $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'LONG', $data, $index, '1h');

                    if (
                        $allowOnHigherTrend
                        && $data[$index]['obv'] > $data[$index - 1]['obv']
                        && $data[$index]['rsi6'] > $data[$index - 1]['rsi6']
                    )
                        return 'LONG';
                    else
                        return null;
                }
            }
        }

        return null;
    }

    // ###############################################################################
    //                     MACD Multi-Step Entry (SHORT)
    // ###############################################################################

    public static function checkConditionSetShortMACD($symbol, $data, $index)
    {
        if (!self::$shortEnabled) {
            return null;
        }

        if (!self::$isBaseReport) {
            $currentAccuracy = self::parseAccuracy(self::$progressionDetailsSHORTMACD, $data[$index]['binance_timestamp'], 6);
            if ($currentAccuracy != -1 && $currentAccuracy < 73) {
                return null;
            }
        }

        $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);

        $steps = [
            [
                'condition' => (
                    $data[$index]['volume'] >= (1.2 * CommonHelpers::getSMAAtIndex($data, $index, 20, 'volume'))
                ),
                'candlesToCheck' => 10,
            ],
            [
                'condition' => (
                    $data[$index]['close'] <= $data[$index]['bb_middle']
                    && $data[$index]['rsi6'] < 65
                    && $bbAnalysis['is_expanding']
                ),
                'candlesToCheck' => 10
            ],
            [
                'condition' => (
                    $bbAnalysis['price_action']['is_near_upper_band']
                    && $data[$index]['rsi6'] >= 55
                    && $data[$index]['rsi6'] <= 75
                    && $data[$index]['volume'] >= $data[$index]['volumeMA5']
                ),
                'candlesToCheck' => 20
            ],
            [
                'condition' => (
                    ($bbAnalysis['price_action']['is_near_upper_band'] || $bbAnalysis['price_action']['crossed_upper_band'])
                    && $data[$index]['rsi6'] >= 80
                    && $data[$index]['volume'] >= (1.5 * $data[$index]['volumeMA10'])
                ),
                'candlesToCheck' => 20
            ],
            [
                'condition' => (
                    $data[$index]['per'] < 0
                    && $data[$index]['high'] > $data[$index]['bb_upper']
                ),
                'candlesToCheck' => 10,
            ],
        ];

        foreach ($steps as $stepIndex => $step) {

            if (!$step['condition']) {
                continue;
            }

            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, 'SHORT');

            if ($stepIndex === 0) {
                if (!$confirmedTrade) {
                    self::insertConfirmBasicTradeEntry($symbol, 'TBD', $data, $index, 'SHORT', $step['candlesToCheck']);
                }
                continue;
            }

            $requiredCheckpoint = $stepIndex - 1;

            if ($confirmedTrade && $confirmedTrade->checkpoints === $requiredCheckpoint) {
                self::updateConfirmTradeCheckpoint($symbol, 'TBD', $data, $index, 'SHORT', $step['candlesToCheck']);

                $isFinal = $stepIndex === count($steps) - 1;

                if ($isFinal) {
                    self::confirmOpening($symbol, 'TBD', $data, $index, 'SHORT');

                    $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'SHORT', $data, $index, '1h');

                    if (
                        $allowOnHigherTrend
                        && $data[$index]['obv'] < $data[$index - 1]['obv']
                        && $data[$index]['rsi6'] < $data[$index - 1]['rsi6']
                    )
                        return 'SHORT';
                    else
                        return null;
                }
            }
        }

        return null;
    }

    // ###############################################################################
    //                     S/R Scoring Entry (LONG)
    // ###############################################################################

    public static function checkConditionSetLongSR($symbol, $data, $index)
    {
        if (!self::$longEnabled) {
            return null;
        }

        $key = $symbol . '_LONG';
        if (isset(self::$srLossCooldown[$key])) {
            $candlesSinceLoss = ($data[$index]['binance_timestamp'] - self::$srLossCooldown[$key]) / (15 * 60 * 1000);
            if ($candlesSinceLoss < 24) {
                return null;
            }
        }

        if (!self::$isBaseReport) {
            $currentAccuracy = self::parseAccuracy(self::$progressionDetailsLONGSR, $data[$index]['binance_timestamp'], 6);
            if ($currentAccuracy != -1 && $currentAccuracy < 75) {
                return null;
            }
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index, 100, 2);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectLongEntryWithSR($symbol, $data, $index, $srAnalysis);

        if ($entry === 'LONG') {
            return $entry;
        }

        return null;
    }

    public static function detectLongEntryWithSR($symbol, $data, $index, $srAnalysis = null)
    {
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];

        $srScore = 0;
        $srConfirmation = false;
        $nearSupport = false;
        $supportDistance = 999;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'buy') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    break;
                }
            }
        }

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'support') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $supportDistance = min($supportDistance, $distance);
                    if ($distance <= 0.005) {
                        $nearSupport = true;
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        $trendScore = 0;
        if ($current['ma7'] > $current['ma14'] && $current['ma14'] > $current['ma25']) {
            $trendScore += 20;
        }
        if ($current['close'] > $current['ma14']) $trendScore += 10;
        if ($current['close'] > $current['ma25']) $trendScore += 10;

        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition < 0.2) $trendScore += 15;
        if ($bbPosition < 0.1) $trendScore += 10;

        $momentumScore = 0;
        if ($current['rsi6'] < 30) $momentumScore += 20;
        if ($current['rsi6'] < 35 && $current['rsi6'] > $prev1['rsi6']) $momentumScore += 15;
        if ($current['rsi6'] > $prev1['rsi6'] && $current['close'] < $prev1['close']) $momentumScore += 10;
        if ($current['stoch_k'] < 20 && $current['stoch_d'] < 20) $momentumScore += 15;
        if ($current['stoch_k'] > $prev1['stoch_k'] && $current['stoch_d'] > $prev1['stoch_d']) $momentumScore += 10;
        if ($current['wr'] < -80) $momentumScore += 10;
        if ($current['dif'] > $current['dea'] && $current['histogram'] > 0) $momentumScore += 10;
        if ($current['histogram'] > $prev1['histogram']) $momentumScore += 10;

        $volumeScore = 0;
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;
        if ($current['obv'] > $prev1['obv']) $volumeScore += 10;
        if ($current['mfi'] > 50 && $current['mfi'] > $prev1['mfi']) $volumeScore += 10;

        $priceActionScore = 0;
        if ($current['close'] > $current['open']) $priceActionScore += 10;
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $bodySize = abs($current['close'] - $current['open']);
        if ($lowerWick > $bodySize * 1.5) $priceActionScore += 15;
        if ($current['low'] < $prev1['low'] && $current['close'] > $prev1['close']) $priceActionScore += 20;
        if ($current['low'] > $prev1['low'] && $prev1['low'] > $data[$index - 2]['low']) $priceActionScore += 10;

        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];
            if ($structure['support_count'] > $structure['resistance_count']) {
                $structureScore += 10;
            }
            if (isset($structure['nearest_support']) && $supportDistance < 0.01) {
                $structureScore += 15;
            }
        }

        $totalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;

        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $volThreshold = self::getVolatilityThreshold($data, $index);
        $highVolatility = $bbWidth > $volThreshold;
        $atrPct = self::getATRPercent($data, $index);
        $tooNoisy = $atrPct > 0.3;
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        if (
            $current['rsi6'] >= 30 &&
            $data[$index - 1]['rsi6'] <= 30 &&
            $current['rsi6'] > $data[$index - 1]['rsi6'] &&
            $nearSupport &&
            $srScore >= 80 &&
            $totalScore >= 60 &&
            !$highVolatility &&
            !$tooNoisy &&
            !$tooFarFromVWAP &&
            $current['histogram'] > $prev1['histogram']
        ) {
            if (!self::checkTrendOnHigherCandles($symbol, 'LONG', $data, $index, '1h')) {
                return null;
            }
            return 'LONG';
        }

        return null;
    }

    // ###############################################################################
    //                     S/R Scoring Entry (SHORT)
    // ###############################################################################

    public static function checkConditionSetShortSR($symbol, $data, $index)
    {
        if (!self::$shortEnabled) {
            return null;
        }

        $key = $symbol . '_SHORT';
        if (isset(self::$srLossCooldown[$key])) {
            $candlesSinceLoss = ($data[$index]['binance_timestamp'] - self::$srLossCooldown[$key]) / (15 * 60 * 1000);
            if ($candlesSinceLoss < 24) {
                return null;
            }
        }

        if (!self::$isBaseReport) {
            $currentAccuracy = self::parseAccuracy(self::$progressionDetailsSHORTSR, $data[$index]['binance_timestamp'], 6);
            if ($currentAccuracy != -1 && $currentAccuracy < 75) {
                return null;
            }
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index, 100, 2);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectShortEntryWithSR($symbol, $data, $index, $srAnalysis);

        if ($entry === 'SHORT') {
            return $entry;
        }

        return null;
    }

    public static function detectShortEntryWithSR($symbol, $data, $index, $srAnalysis = null)
    {
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];

        $srScore = 0;
        $srConfirmation = false;
        $nearResistance = false;
        $resistanceDistance = 999;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'sell') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    break;
                }
            }
        }

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'resistance') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $resistanceDistance = min($resistanceDistance, $distance);
                    if ($distance <= 0.005) {
                        $nearResistance = true;
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        $trendScore = 0;
        if ($current['ma7'] < $current['ma14'] && $current['ma14'] < $current['ma25']) {
            $trendScore += 20;
        }
        if ($current['close'] < $current['ma14']) $trendScore += 10;
        if ($current['close'] < $current['ma25']) $trendScore += 10;

        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition > 0.8) $trendScore += 15;
        if ($bbPosition > 0.9) $trendScore += 10;

        $momentumScore = 0;
        if ($current['rsi6'] > 70) $momentumScore += 20;
        if ($current['rsi6'] > 65 && $current['rsi6'] < $prev1['rsi6']) $momentumScore += 15;
        if ($current['rsi6'] < $prev1['rsi6'] && $current['close'] > $prev1['close']) $momentumScore += 10;
        if ($current['stoch_k'] > 80 && $current['stoch_d'] > 80) $momentumScore += 15;
        if ($current['stoch_k'] < $prev1['stoch_k'] && $current['stoch_d'] < $prev1['stoch_d']) $momentumScore += 10;
        if ($current['wr'] > -20) $momentumScore += 10;
        if ($current['dif'] < $current['dea'] && $current['histogram'] < 0) $momentumScore += 10;
        if ($current['histogram'] < $prev1['histogram']) $momentumScore += 10;

        $volumeScore = 0;
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;
        if ($current['obv'] < $prev1['obv']) $volumeScore += 10;
        if ($current['mfi'] < 50 && $current['mfi'] < $prev1['mfi']) $volumeScore += 10;

        $priceActionScore = 0;
        if ($current['close'] < $current['open']) $priceActionScore += 10;
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $bodySize = abs($current['close'] - $current['open']);
        if ($upperWick > $bodySize * 1.5) $priceActionScore += 15;
        if ($current['high'] > $prev1['high'] && $current['close'] < $prev1['close']) $priceActionScore += 20;
        if ($current['high'] < $prev1['high'] && $prev1['high'] < $data[$index - 2]['high']) $priceActionScore += 10;

        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];
            if ($structure['resistance_count'] > $structure['support_count']) {
                $structureScore += 10;
            }
            if (isset($structure['nearest_resistance']) && $resistanceDistance < 0.01) {
                $structureScore += 15;
            }
        }

        $totalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;

        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $volThreshold = self::getVolatilityThreshold($data, $index);
        $highVolatility = $bbWidth > $volThreshold;
        $atrPct = self::getATRPercent($data, $index);
        $tooNoisy = $atrPct > 0.3;
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        if (
            $current['rsi6'] <= 65 &&
            $data[$index - 1]['rsi6'] >= 65 &&
            $current['rsi6'] < $data[$index - 1]['rsi6'] &&
            $nearResistance &&
            $srScore >= 80 &&
            $totalScore >= 60 &&
            !$highVolatility &&
            !$tooNoisy &&
            !$tooFarFromVWAP &&
            $current['histogram'] < $prev1['histogram']
        ) {
            if (!self::checkTrendOnHigherCandles($symbol, 'SHORT', $data, $index, '1h')) {
                return null;
            }
            return 'SHORT';
        }

        return null;
    }

    // ###############################################################################
    //                      Opening Conditions FVG Zones (Enhanced)
    // ###############################################################################

    public static function checkFVGZoneEntry($symbol, $data, $index)
    {
        $fvg = self::$currentFVG;

        if (!$fvg) {
            return null;
        }

        // Skip if FVG already invalidated (price already broke through it)
        if (isset($fvg['filledIndex']) && $fvg['filledIndex'] !== null) {
            return null;
        }

        // Minimum gap strength filter - skip tiny gaps
        if ($fvg['distance'] < 0.8) {
            return null;
        }

        // Volume confirmation
        $volumeOk = $data[$index]['volume'] >= $data[$index]['volumeMA5'];

        if ($fvg['type'] === 'bullish') {
            if (
                $data[$index]['close'] > $fvg['top']
                && $data[$index]['low'] < $fvg['top']
                && $volumeOk
            ) {
                // Trend filter: close above MA14 for LONG
                if ($data[$index]['close'] < $data[$index]['ma14']) {
                    return null;
                }

                self::$dynamicSL = $fvg['bottom'] * (1 - 0.2 / 100);
                self::$dynamicTP = $data[$index]['close'] + (($data[$index]['close'] - self::$dynamicSL) * 2);
                return 'LONG';
            }
        } else {
            if (
                $data[$index]['close'] < $fvg['bottom']
                && $data[$index]['high'] > $fvg['bottom']
                && $volumeOk
            ) {
                // Trend filter: close below MA14 for SHORT
                if ($data[$index]['close'] > $data[$index]['ma14']) {
                    return null;
                }

                self::$dynamicSL = $fvg['top'] * (1 + 0.2 / 100);
                self::$dynamicTP = $data[$index]['close'] - ((self::$dynamicSL - $data[$index]['close']) * 2);
                return 'SHORT';
            }
        }
    }

    // ###############################################################################
    //                         Closing Logic (Trailing TP/SL + Early Exit)
    // ###############################################################################

    public static function handleClosingConditions($symbol, $data, $index)
    {
        $closingPrice = 0;
        $candle = $data[$index];
        $candlesPast = self::getIndexDiffFromTimestamps($data[self::$openingIndex]['binance_timestamp'], $data[$index]['binance_timestamp'], self::$interval);

        if (self::$tradeType === 'LONG') {
            if ($candle['close'] >= self::$dynamicTP) {
                self::$dynamicTP = $candle['close'] * (1 + self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $candle['close'] * (1 - (self::$dynamicTPSLgap / 2) / 100);
            } else if ($candle['close'] < self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        } else {
            if ($candle['close'] <= self::$dynamicTP) {
                self::$dynamicTP = $candle['close'] * (1 - self::$dynamicTPSLgap / 100);
                self::$dynamicSL = $candle['close'] * (1 + (self::$dynamicTPSLgap / 2) / 100);
            } else if ($candle['close'] > self::$dynamicSL) {
                $closingPrice = self::$dynamicSL;
            }
        }

        if (!$closingPrice && $candlesPast <= 3) {
            if (self::$tradeType === 'LONG') {
                if (
                    $data[$index]['close'] < $data[$index]['bb_lower']
                    && $data[$index - 1]['close'] < $data[$index - 1]['bb_lower']
                ) {
                    $closingPrice = $data[$index]['close'];
                }
            } else if (self::$tradeType === 'SHORT') {
                if (
                    $data[$index]['close'] > $data[$index]['bb_upper']
                    && $data[$index - 1]['close'] > $data[$index - 1]['bb_upper']
                ) {
                    $closingPrice = $data[$index]['close'];
                }
            }
        }

        return $closingPrice;
    }

    // ###############################################################################
    //                         Levels Adjustment
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
                            'bottom' => $data4h[$loopIndex]['low'],
                            'timestamp' => $data4h[$loopIndex]['binance_timestamp'],
                            'timestamp_pst' => $data4h[$loopIndex]['timestamp_pst'],
                            'timestampReadable' => $data4h[$loopIndex]['timestampReadable'],
                        ];
                    }
                } else {
                    self::$sLevels[] = [
                        'top' => $data4h[$loopIndex]['high'],
                        'bottom' => $data4h[$loopIndex]['low'],
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

    public static function getATRPercent($data, $index)
    {
        $atr14 = $data[$index]['atr14'] ?? null;
        if ($atr14 && $data[$index]['close'] > 0) {
            return $atr14 / $data[$index]['close'] * 100;
        }
        $atr14Prev = $data[$index - 1]['atr14'] ?? null;
        if ($atr14Prev && $data[$index - 1]['close'] > 0) {
            return $atr14Prev / $data[$index - 1]['close'] * 100;
        }
        return 0.8;
    }

    public static function getVolatilityThreshold($data, $index)
    {
        $atrPct = self::getATRPercent($data, $index);
        return max(0.04, min(0.15, $atrPct * 0.04));
    }

    // ###############################################################################
    //                     Support/Resistance Helper
    // ###############################################################################

    public static function getSupportResistance($data, $index)
    {
        $end = $index + 1;
        $length = 300;
        $start = max(0, $end - $length);
        $slicedData = array_slice($data, $start, $length);

        return MarketTrendService::getCurrentSupportResistanceValueFromData($slicedData, [self::$supportResistanceCandleSpan])[self::$supportResistanceCandleSpan];
    }

    // ###############################################################################
    //                     Higher Timeframe Trend Check
    // ###############################################################################

    public static function checkTrendOnHigherCandles($symbol, $position, $data, $index, $higherInterval = '1h')
    {
        $dataHigher = BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE');
        $indexHigher = count($dataHigher) - 2;
        $current = $dataHigher[$indexHigher];

        $loopIndex = $indexHigher;
        $crossOverFound = false;
        $crossOverType = null;

        while ($loopIndex > 0) {
            $difCurrent = $dataHigher[$loopIndex]['dif'];
            $deaCurrent = $dataHigher[$loopIndex]['dea'];
            $difPrev = $dataHigher[$loopIndex - 1]['dif'];
            $deaPrev = $dataHigher[$loopIndex - 1]['dea'];

            if ($difCurrent > $deaCurrent && $difPrev <= $deaPrev) {
                $crossOverFound = true;
                $crossOverType = 'bullish';
                break;
            } else if ($difCurrent < $deaCurrent && $difPrev >= $deaPrev) {
                $crossOverFound = true;
                $crossOverType = 'bearish';
                break;
            }
            $loopIndex--;
        }

        if ($position === 'LONG') {
            if ($crossOverFound) {
                if ($crossOverType === 'bearish') {
                    $bbMiddleCondition = $current['bb_middle'] <= $dataHigher[$indexHigher - 1]['bb_middle'];
                    if ($bbMiddleCondition) {
                        return false;
                    }
                }
                return true;
            }
            return $current['dif'] >= $current['dea'];
        } else {
            if ($crossOverFound) {
                if ($crossOverType === 'bullish') {
                    $bbMiddleCondition = $current['bb_middle'] >= $dataHigher[$indexHigher - 1]['bb_middle'];
                    if ($bbMiddleCondition) {
                        return false;
                    }
                }
                return true;
            }
            return $current['dif'] <= $current['dea'];
        }
    }

    // ###############################################################################
    //                         4h Trend Strength Check
    // ###############################################################################

    public static function checkStrongTrend($symbol, $position, $data, $index)
    {
        $data4h = BinanceApiService::getCandleStickDataPast($symbol, '4h', 30, $data[$index]['binance_timestamp'], 'FUTURE');
        $end = count($data4h) - 2;

        $bullCount = 0;
        $bearCount = 0;
        for ($i = $end; $i > max(0, $end - 6); $i--) {
            if ($data4h[$i]['dif'] > $data4h[$i]['dea']) $bullCount++;
            else $bearCount++;
        }

        $current = $data4h[$end];
        $prev = $data4h[$end - 1];
        $macdBullMajority = $bullCount > $bearCount;
        $histogramGrowing = abs($current['histogram']) > abs($prev['histogram']);
        $histogramPositive = $current['histogram'] > 0;

        if ($position === 'SHORT') {
            if ($macdBullMajority && $histogramGrowing && $histogramPositive) {
                return false;
            }
            return true;
        } else {
            if (!$macdBullMajority && $histogramGrowing && !$histogramPositive) {
                return false;
            }
            return true;
        }
    }

    // ###############################################################################
    //                         Confirmed Trade Helpers
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
        $id = DB::table('confirmed_trades')->insertGetId([
            'coin_name' => $symbol,
            'type' => $type,
            'intention' => $intention,
            'formula' => self::$formula,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'checkpoint_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => $candlesToCheck,
            'trade_confirmed' => 0,
            'checkpoints' => 0,
            'bolling_last_squeez_value' => null,
            'bolling_last_squeezed_timestamp' => null,
            'update_time' => Carbon::now()->toDateTimeString(),
        ]);

        return DB::table('confirmed_trades')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol, $position, $intention = null)
    {
        $lastEntry = DB::table('confirmed_trades')->where('coin_name', $symbol)->where('type', $position);

        if ($intention) {
            $lastEntry->where('intention', $intention);
        }
        $lastEntry = $lastEntry->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }

    public static function checkConfirmTradeValidity($symbol, $type, $data, $index, $intention = null)
    {
        $ictId = self::getIctId($symbol, $type, $intention);
        if (!$ictId) {
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
        if (!$ictId) {
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

    // ###############################################################################
    //                     Progression & Accuracy Tracking
    // ###############################################################################

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

            $currentUnixMillis = round(microtime(true) * 1000);

            if ($endUnix > $currentUnixMillis) {
                $endUnix = $currentUnixMillis;
            }

            $startDateStr = date('F j, Y', $startUnix / 1000);
            $endDateStr = date('F j, Y', $endUnix / 1000);

            $dateRange = $startDateStr . ' to ' . $endDateStr;
        }

        // Load progression details from base report if available
        if (self::$baseReportFormula) {
            self::$timeWiseTradesCount = self::getTimestampWiseProfitableTrades(self::$baseReportFormula, $endUnix);
            self::$progressionDetailsLONGMACD = self::getProgressionDetails(self::$baseReportFormula, 'LONG', $endUnix, 'MACD');
            self::$progressionDetailsLONGSR = self::getProgressionDetails(self::$baseReportFormula, 'LONG', $endUnix, 'SR');

            self::$progressionDetailsSHORTMACD = self::getProgressionDetails(self::$baseReportFormula, 'SHORT', $endUnix, 'MACD');
            self::$progressionDetailsSHORTSR = self::getProgressionDetails(self::$baseReportFormula, 'SHORT', $endUnix, 'SR');
        }

        $classPath = app_path('Services/InternalTrader/ReportServiceImproved.php');
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
                            </tr>
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
            'initialTpPercent' => self::$initialTpPercent,
            'initialSlPercent' => self::$initialSlPercent,
            'dynamicTPSLgap' => self::$dynamicTPSLgap,
            'earlyClosingEnabled' => self::$earlyClosingEnabled,
            'startUnix' => $startUnix,
            'endUnix' => $endUnix,
            'startDateStr' => $startDateStr,
            'endDateStr' => $endDateStr,
            'dateRange' => $dateRange,
            'trendReferenceSymbol' => self::$trendReferenceSymbol,
            'trendReferenceInterval' => self::$trendReferenceInterval,
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

    public static function getTimestampWiseProfitableTrades($formula, $binance_timestamp)
    {
        $trades = DB::table('coin_reports')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.binance_timestamp')) as buying_timestamp, COUNT(*) as trade_count")
            ->where('formula', $formula)
            ->whereNotNull('sellingCandle')
            ->whereRaw("JSON_EXTRACT(sellingCandle, '$.binance_timestamp') <= ?", [$binance_timestamp])
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

    // ###############################################################################
    //                         Reset
    // ###############################################################################

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

    protected static function fetchCandlesWithRetry($symbol, $interval, $limit, $timestamp, $retries = 3)
    {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $result = BinanceApiService::getCandleStickDataPast($symbol, $interval, $limit, $timestamp, 'FUTURE');
                if (!empty($result) && is_array($result) && !is_null($result[0]['binance_timestamp'] ?? null)) {
                    return $result;
                }
                if ($attempt < $retries) {
                    CommonHelpers::delayMS(2000 * $attempt);
                }
            } catch (\Exception $e) {
                Log::warning("fetchCandlesWithRetry attempt {$attempt} failed for {$symbol} {$interval}: " . $e->getMessage());
                if ($attempt < $retries) {
                    CommonHelpers::delayMS(2000 * $attempt);
                }
            }
        }
        return [];
    }
}
