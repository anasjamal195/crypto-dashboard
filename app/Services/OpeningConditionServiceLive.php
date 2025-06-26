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
                'formula' => 'MACD & SR - 15m'
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
                'formula' => 'MACD & SR - 15m'
            ];
        }

        // LONG ENTRY
        if (
            self::checkConditionSetLongMACD15m($symbol, $data, $index) === 'LONG'
        ) {
            return [
                'direction' => 'LONG',
                'formula' => 'MACD - 15m'
            ];
        }

        if (
            self::checkConditionSetLongSR15m($symbol, $data, $index) === 'LONG'
        ) {
            return [
                'direction' => 'LONG',
                'formula' => 'SR - 15m'
            ];
        }

        // SHORT ENTRY
        if (
            self::checkConditionSetShortMACD15m($symbol, $data, $index) === 'SHORT'

        ) {
            return [
                'direction' => 'SHORT',
                'formula' => 'MACD - 15m'
            ];
        }
        if (
            self::checkConditionSetShortSR15m($symbol, $data, $index) === 'SHORT'
        ) {
            return [
                'direction' => 'SHORT',
                'formula' => 'SR - 15m'
            ];
        }

        return [
            'direction' => null,
            'formula' => 'MACD & SR - 15m'
        ];
    }

    public static function getOpeningOn5m($symbol)
    {

        $interval = '5m';
        $cacheKey = "last_checked_for_opening_{$symbol}_{$interval}";

        // if (Cache::get($cacheKey, 0)) {
        //     return [
        //         'direction' => null,
        //         'formula' => 'MACD & SR - 5m'
        //     ];
        // }

        $data = self::$activeExchange === 'binance' ?
            BinanceApiService::getCandleStickDataExternal($symbol, $interval, 500, null,  'FUTURE')
            : HyperLiquidApiService::getCandleStickDataExternal($symbol, $interval, 500, null, 'FUTURE');


        $index = count($data) - 2;


        $cacheValue = time() * 1000;

        $now = now();
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $minutesToNextRounded = $intervalToMins - ($now->minute % $intervalToMins);
        $nextRoundedTime = $now->copy()->addMinutes($minutesToNextRounded)->startOfMinute();


        Cache::put($cacheKey, $cacheValue, $nextRoundedTime);

        // Check candle closing
        if (!CommonHelpers::checkCandleClosing($data, 300)) {
            return [
                'direction' => null,
                'formula' => 'MACD & SR - 5m'
            ];
        }

        // LONG ENTRY
        if (
            self::checkConditionSetLongMACD5m($symbol, $data, $index) === 'LONG'

        ) {
            return [
                'direction' => 'LONG',
                'formula' => 'MACD - 5m'
            ];
        }
        // if (
        //     self::checkConditionSetLongSR5m($symbol, $data, $index) === 'LONG'
        // ) {
        //     return [
        //         'direction' => 'LONG',
        //         'formula' => 'SR - 5m'
        //     ];
        // }

        // SHORT ENTRY
        if (
            self::checkConditionSetShortMACD5m($symbol, $data, $index) === 'SHORT'
        ) {
            return [
                'direction' => 'SHORT',
                'formula' => 'MACD - 5m'
            ];
        }
        // if (
        //     self::checkConditionSetShortSR5m($symbol, $data, $index) === 'SHORT'
        // ) {
        //     return [
        //         'direction' => 'SHORT',
        //         'formula' => 'SR - 5m'
        //     ];
        // }


        return [
            'direction' => null,
            'formula' => 'MACD & SR - 5m'
        ];
    }















    // 5m Candle opening conditions

    public static function detectLongEntryWithSR15m($data, $index, $srAnalysis = null)
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
    public static function detectShortEntryWithSR15m($data, $index, $srAnalysis = null)
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

        // ADX trend strength
        $weakTrend = $current['adx'] < 20;

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

        // Premium conditions (highest accuracy)
        $premiumConditionsMet = $enhancedConditionsMet &&
            ($resistanceStrength >= 80) && // Strong resistance
            ($totalScore >= 100) && // High combined score
            ($riskReward >= 1.5); // Good risk/reward

        // === RETURN SIGNAL ===

        if ($data[$index]['rsi6'] <= 65 && $data[$index - 1]['rsi6'] >= 65 && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] &&  $nearResistance && $srScore >= 70) {
            return 'SHORT';
        }
        return null;

        if ($premiumConditionsMet) {
            return [
                'signal' => 'SHORT',
                'confidence' => min(95, $totalScore),
                'entry_price' => $current['close'],
                'stop_loss' => $suggestedSL ?? ($current['high'] * 1.005),
                'take_profit_1' => $suggestedTP ?? ($current['close'] * 0.98),
                'take_profit_2' => $current['close'] * 0.96,
                'risk_reward' => $riskReward,
                'analysis' => [
                    'technical_score' => $totalTechnicalScore,
                    'sr_score' => $srScore,
                    'total_score' => $totalScore,
                    'near_resistance' => $nearResistance,
                    'resistance_strength' => $resistanceStrength,
                    'conditions_met' => 'premium'
                ]
            ];
        } elseif ($enhancedConditionsMet) {
            return [
                'signal' => 'SHORT',
                'confidence' => min(85, $totalScore * 0.9),
                'entry_price' => $current['close'],
                'stop_loss' => $suggestedSL ?? ($current['high'] * 1.003),
                'take_profit_1' => $suggestedTP ?? ($current['close'] * 0.985),
                'take_profit_2' => $current['close'] * 0.97,
                'risk_reward' => $riskReward,
                'analysis' => [
                    'technical_score' => $totalTechnicalScore,
                    'sr_score' => $srScore,
                    'total_score' => $totalScore,
                    'near_resistance' => $nearResistance,
                    'resistance_strength' => $resistanceStrength,
                    'conditions_met' => 'enhanced'
                ]
            ];
        }

        return null;
    }



    public static function checkConditionSetLongSR15m($symbol, $data, $index)
    {

        $accuracyStatsSR = CommonHelpers::getAccuracy('LONG', 'Base Report - 15m', 'SR');
        if ($accuracyStatsSR['accuracy'] < 75 && $accuracyStatsSR['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsSR['accuracy']  . 'SR LONG: ' . $symbol);
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectLongEntryWithSR15m($data, $index, $srAnalysis);


        if ($entry === 'LONG') {
            return $entry;
        }

        return null;
    }

    public static function checkConditionSetLongMACD15m($symbol, $data, $index)
    {

        $interval = '15m';
        $accuracyStatsMACD = CommonHelpers::getAccuracy('LONG', 'Base Report - 15m', 'MACD');;
        if ($accuracyStatsMACD['accuracy'] < 73 && $accuracyStatsMACD['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy ' . $accuracyStatsMACD['accuracy']  . 'MACD LONG: ' . $symbol);
            return null;
        }

        if (
            $data[$index]['histogram'] > $data[$index - 1]['histogram'] && $data[$index]['histogram'] < 0

            && $data[$index - 1]['histogram'] < $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] < 0
            && $data[$index - 2]['histogram'] < $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] < 0
            && $data[$index - 3]['histogram'] < $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] < 0
            && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index, $interval)
        ) {
            self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index, $interval)) {
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $buyCondition =
                (
                    $data[$index]['rsi6'] < 30
                    && $data[$index]['rsi6'] > $data[$index - 1]['rsi6']
                    && $bbAnalysis['price_action']['is_near_lower_band']
                    && $data[$index]['close'] > $data[$index]['bb_lower']
                    && $data[$index]['open'] < $data[$index]['bb_lower']
                );

            if ($buyCondition) {
                self::confirmOpening($symbol, 'LONG', $data, $index);
                return 'LONG';
            }
        }

        return null;
    }

    public static function checkConditionSetShortSR15m($symbol, $data, $index)
    {


        $accuracyStatsSR = CommonHelpers::getAccuracy('SHORT', 'Base Report - 15m', 'SR');
        if ($accuracyStatsSR['accuracy'] < 75 && $accuracyStatsSR['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsSR['accuracy']  . ' SR SHORT: ' . $symbol);
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectShortEntryWithSR15m($data, $index, $srAnalysis);

        if ($entry === 'SHORT')
            return $entry;

        return null;
    }


    public static function checkConditionSetShortMACD15m($symbol, $data, $index)
    {


        $interval = '15m';
        $accuracyStatsMACD = CommonHelpers::getAccuracy('SHORT', 'Base Report - 15m', 'MACD');
        if ($accuracyStatsMACD['accuracy'] < 73 && $accuracyStatsMACD['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsMACD['accuracy']  . 'MACD SHORT: ' . $symbol);
            return null;
        }

        if (
            $data[$index]['histogram'] < $data[$index - 1]['histogram'] && $data[$index]['histogram'] > 0
            && $data[$index - 1]['histogram'] > $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] > 0
            && $data[$index - 2]['histogram'] > $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] > 0
            && $data[$index - 3]['histogram'] > $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] > 0

            && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index, $interval)

        ) {
            self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index, $interval)) {
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $buyCondition =
                (
                    $data[$index]['rsi6'] > 70
                    && $data[$index]['rsi6'] < $data[$index - 1]['rsi6']
                    && $bbAnalysis['price_action']['is_near_upper_band']
                    && $data[$index]['close'] < $data[$index]['bb_upper']
                    && $data[$index]['open'] > $data[$index]['bb_upper']
                );

            if ($buyCondition) {
                self::confirmOpening($symbol, 'SHORT', $data, $index);

                return 'SHORT';
            }
        }

        return null;
    }




    // ########################## SR ENTRY #####################################


    public static function detectLongEntryWithSR5m($data, $index, $srAnalysis = null)
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

        // === SPECIFIC ENTRY SIGNAL FOR 5m CANDLES ===
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
    public static function detectShortEntryWithSR5m($data, $index, $srAnalysis = null)
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

        // ADX trend strength
        $weakTrend = $current['adx'] < 20;

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

        // Premium conditions (highest accuracy)
        $premiumConditionsMet = $enhancedConditionsMet &&
            ($resistanceStrength >= 80) && // Strong resistance
            ($totalScore >= 100) && // High combined score
            ($riskReward >= 1.5); // Good risk/reward

        // === RETURN SIGNAL ===

        if ($data[$index]['rsi6'] <= 70 && $data[$index - 1]['rsi6'] >= 70 && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] &&  $nearResistance && $srScore >= 75) {
            return 'SHORT';
        }

        return null;
    }


    public static function checkConditionSetLongSR5m($symbol, $data, $index)
    {

        $accuracyStatsSR = CommonHelpers::getAccuracy('LONG', 'Base Report - 5m', 'SR');
        if ($accuracyStatsSR['accuracy'] < 77 && $accuracyStatsSR['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsSR['accuracy']  . 'SR LONG: ' . $symbol);
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectLongEntryWithSR5m($data, $index, $srAnalysis);

        if ($entry === 'LONG') {
            return $entry;
        }

        return null;
    }

    public static function checkConditionSetLongMACD5m($symbol, $data, $index)
    {

        $interval = '5m';

        $accuracyStatsMACD = CommonHelpers::getAccuracy('LONG', 'Base Report - 5m', 'MACD');

        if ($accuracyStatsMACD['accuracy'] < 80 && $accuracyStatsMACD['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsMACD['accuracy']  . 'MACD SHORT: ' . $symbol);
            return null;
        }

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


                    $data[$index]['close'] >= $data[$index]['bb_middle']
                    && $data[$index]['rsi6'] > 45
                    && $bbAnalysis['is_expanding']


                ),
                'candlesToCheck' => 10
            ],

            // Step 3 - Setup Formation
            [
                'condition' => (


                    $bbAnalysis['price_action']['is_near_lower_band']
                    && $data[$index]['rsi6'] >= 25
                    && $data[$index]['rsi6'] <= 45
                    && $data[$index]['volume'] >= $data[$index]['volumeMA5']


                ),
                'candlesToCheck' => 20
            ],

            // Step 4 - Bullish Candle Check
            [
                'condition' => (
                    ($bbAnalysis['price_action']['is_near_lower_band'] || $bbAnalysis['price_action']['crossed_lower_band'])
                    && $data[$index]['rsi6'] <= 20
                    && $data[$index]['volume'] >= (1.5 * $data[$index]['volumeMA10'])
                ),
                'candlesToCheck' => 20
            ],

            // Final Step - Entry Execution
            [
                'condition' => (

                    $data[$index]['per'] > 0
                    && $data[$index]['low'] < $data[$index]['bb_lower']
                ),
                'candlesToCheck' => 10,
            ],
        ];

        // Process steps sequentially
        foreach ($steps as $stepIndex => $step) {


            if (!$step['condition']) {
                continue;
            }


            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, $interval,'LONG');

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
                    )
                        return 'LONG';
                    else
                        return null;
                }
            }
        }

        return null;
    }

    public static function checkConditionSetShortSR5m($symbol, $data, $index)
    {



        $accuracyStatsSR = CommonHelpers::getAccuracy('SHORT', 'Base Report - 5m', 'SR');
        if ($accuracyStatsSR['accuracy'] < 77 && $accuracyStatsSR['accuracy'] != -1) {
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();



        $entry = self::detectShortEntryWithSR5m($data, $index, $srAnalysis);

        if ($entry === 'SHORT')
            return $entry;

        return null;
    }


    public static function checkConditionSetShortMACD5m($symbol, $data, $index)
    {


        $interval = '5m';
        $accuracyStatsMACD = CommonHelpers::getAccuracy('SHORT', 'Base Report - 5m', 'MACD');
        if ($accuracyStatsMACD['accuracy'] < 80 && $accuracyStatsMACD['accuracy'] != -1) {
            // Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $accuracyStatsMACD['accuracy']  . 'MACD SHORT: ' . $symbol);
            return null;
        }

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


            $confirmedTrade = self::checkConfirmTradeValidity($symbol, 'TBD', $data, $index, $interval,'SHORT');

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
