<?php

namespace App\Services;

class PatternDetector
{
    // Configuration constants
    const MIN_BODY_RATIO = 0.6; // For strong body candles
    const DOJI_BODY_RATIO = 0.1; // For doji identification
    const HAMMER_SHADOW_RATIO = 2.0; // Shadow should be 2x body size
    const ENGULFING_MIN_RATIO = 1.2; // Engulfing body should be 20% larger
    const VOLUME_SPIKE_THRESHOLD = 1.5; // Volume should be 50% above average
    const RSI_OVERSOLD = 30;
    const RSI_OVERBOUGHT = 70;

    /**
     * Main function to analyze patterns and return signal strength for both LONG and SHORT
     */
    public static function analyzeEntry($data, $index, $direction = 'BOTH', $supportResistance = null)
    {
        if ($index < 20) return null; // Need enough history

        $patterns = [
            'candlestick_patterns' => self::detectCandlestickPatterns($data, $index),
            'chart_patterns' => self::detectChartPatterns($data, $index),
            'support_resistance' => self::analyzeSupportResistance($data, $index, $supportResistance),
            'momentum' => self::analyzeMomentum($data, $index),
            'volume_analysis' => self::analyzeVolume($data, $index)
        ];

        $result = [];

        if ($direction === 'LONG' || $direction === 'BOTH') {
            $longSignal = self::calculateSignalStrength($patterns, $data, $index, 'LONG');
            $result['LONG'] = [
                'signal_strength' => $longSignal['strength'],
                'confidence' => $longSignal['confidence'],
                'entry_reason' => $longSignal['reasons'],
                'stop_loss_suggestion' => $longSignal['stop_loss'],
                'take_profit_suggestion' => $longSignal['take_profit'],
                'risk_reward_ratio' => $longSignal['risk_reward']
            ];
        }

        if ($direction === 'SHORT' || $direction === 'BOTH') {
            $shortSignal = self::calculateSignalStrength($patterns, $data, $index, 'SHORT');
            $result['SHORT'] = [
                'signal_strength' => $shortSignal['strength'],
                'confidence' => $shortSignal['confidence'],
                'entry_reason' => $shortSignal['reasons'],
                'stop_loss_suggestion' => $shortSignal['stop_loss'],
                'take_profit_suggestion' => $shortSignal['take_profit'],
                'risk_reward_ratio' => $shortSignal['risk_reward']
            ];
        }

        // Add overall recommendation
        if ($direction === 'BOTH') {
            $result['recommendation'] = self::getOverallRecommendation($result);
        }

        $result['patterns_detected'] = $patterns;
        $result['timestamp'] = date('Y-m-d H:i:s');

        return $result;
    }

    /**
     * Legacy function for backward compatibility
     */
    public static function analyzeLongEntry($data, $index, $supportResistance = null)
    {
        $result = self::analyzeEntry($data, $index, 'LONG', $supportResistance);
        return $result['LONG'] ?? null;
    }

    /**
     * Detect various candlestick patterns (both bullish and bearish)
     */
    private static function detectCandlestickPatterns($data, $index)
    {
        $patterns = [];

        // Single candle patterns
        $patterns['hammer'] = self::isHammer($data, $index);
        $patterns['hanging_man'] = self::isHangingMan($data, $index);
        $patterns['inverted_hammer'] = self::isInvertedHammer($data, $index);
        $patterns['shooting_star'] = self::isShootingStar($data, $index);
        $patterns['doji'] = self::isDoji($data, $index);
        $patterns['spinning_top'] = self::isSpinningTop($data, $index);
        $patterns['bullish_marubozu'] = self::isBullishMarubozu($data, $index);
        $patterns['bearish_marubozu'] = self::isBearishMarubozu($data, $index);

        // Two candle patterns
        if ($index >= 1) {
            $patterns['bullish_engulfing'] = self::isBullishEngulfing($data, $index);
            $patterns['bearish_engulfing'] = self::isBearishEngulfing($data, $index);
            $patterns['piercing_line'] = self::isPiercingLine($data, $index);
            $patterns['dark_cloud_cover'] = self::isDarkCloudCover($data, $index);
            $patterns['tweezer_bottom'] = self::isTweezerBottom($data, $index);
            $patterns['tweezer_top'] = self::isTweezerTop($data, $index);
            $patterns['harami_bullish'] = self::isHaramiBullish($data, $index);
            $patterns['harami_bearish'] = self::isHaramiBearish($data, $index);
        }

        // Three candle patterns
        if ($index >= 2) {
            $patterns['morning_star'] = self::isMorningStar($data, $index);
            $patterns['evening_star'] = self::isEveningStar($data, $index);
            $patterns['three_white_soldiers'] = self::isThreeWhiteSoldiers($data, $index);
            $patterns['three_black_crows'] = self::isThreeBlackCrows($data, $index);
            $patterns['bullish_abandoned_baby'] = self::isBullishAbandonedBaby($data, $index);
            $patterns['bearish_abandoned_baby'] = self::isBearishAbandonedBaby($data, $index);
        }

        return array_filter($patterns); // Remove false values
    }

    /**
     * Detect chart patterns (both bullish and bearish)
     */
    private static function detectChartPatterns($data, $index)
    {
        $patterns = [];

        if ($index >= 20) {
            $patterns['ascending_triangle'] = self::isAscendingTriangle($data, $index);
            $patterns['descending_triangle'] = self::isDescendingTriangle($data, $index);
            $patterns['cup_and_handle'] = self::isCupAndHandle($data, $index);
            $patterns['inverted_cup_and_handle'] = self::isInvertedCupAndHandle($data, $index);
            $patterns['double_bottom'] = self::isDoubleBottom($data, $index);
            $patterns['double_top'] = self::isDoubleTop($data, $index);
            $patterns['falling_wedge'] = self::isFallingWedge($data, $index);
            $patterns['rising_wedge'] = self::isRisingWedge($data, $index);
            $patterns['bullish_flag'] = self::isBullishFlag($data, $index);
            $patterns['bearish_flag'] = self::isBearishFlag($data, $index);
            $patterns['head_and_shoulders'] = self::isHeadAndShoulders($data, $index);
            $patterns['inverse_head_and_shoulders'] = self::isInverseHeadAndShoulders($data, $index);
        }

        return array_filter($patterns);
    }

    /**
     * Analyze support and resistance levels
     */
    private static function analyzeSupportResistance($data, $index, $supportResistance)
    {
        $current = $data[$index];
        $analysis = [];

        // Find recent support and resistance levels
        $support_levels = self::findSupportLevels($data, $index, 20);
        $resistance_levels = self::findResistanceLevels($data, $index, 20);

        $analysis['near_support'] = self::isNearLevel($current['close'], $support_levels, 0.5);
        $analysis['near_resistance'] = self::isNearLevel($current['close'], $resistance_levels, 0.5);
        $analysis['support_breakout'] = self::isSupportBreakout($data, $index, $support_levels);
        $analysis['support_breakdown'] = self::isSupportBreakdown($data, $index, $support_levels);
        $analysis['resistance_breakout'] = self::isResistanceBreakout($data, $index, $resistance_levels);
        $analysis['resistance_retest'] = self::isResistanceRetest($data, $index, $resistance_levels);

        return $analysis;
    }

    /**
     * Analyze momentum indicators
     */
    private static function analyzeMomentum($data, $index)
    {
        $current = $data[$index];
        $momentum = [];

        // RSI analysis
        $momentum['rsi_oversold_recovery'] = $current['rsi6'] > self::RSI_OVERSOLD &&
            $data[$index - 1]['rsi6'] <= self::RSI_OVERSOLD;
        $momentum['rsi_overbought_decline'] = $current['rsi6'] < self::RSI_OVERBOUGHT &&
            $data[$index - 1]['rsi6'] >= self::RSI_OVERBOUGHT;
        $momentum['rsi_bullish'] = $current['rsi6'] > 50 && $current['rsi6'] < 70;
        $momentum['rsi_bearish'] = $current['rsi6'] < 50 && $current['rsi6'] > 30;

        // MACD analysis
        $momentum['macd_bullish_cross'] = $current['histogram'] > 0 &&
            $data[$index - 1]['histogram'] <= 0;
        $momentum['macd_bearish_cross'] = $current['histogram'] < 0 &&
            $data[$index - 1]['histogram'] >= 0;
        $momentum['macd_bullish_momentum'] = $current['histogram'] > $data[$index - 1]['histogram'];
        $momentum['macd_bearish_momentum'] = $current['histogram'] < $data[$index - 1]['histogram'];

        // Stochastic analysis
        $momentum['stoch_oversold_recovery'] = $current['stoch_rsi'] > 0.2 &&
            $data[$index - 1]['stoch_rsi'] <= 0.2;
        $momentum['stoch_overbought_decline'] = $current['stoch_rsi'] < 0.8 &&
            $data[$index - 1]['stoch_rsi'] >= 0.8;

        // ADX for trend strength
        $momentum['strong_trend'] = $current['adx'] > 25;
        $momentum['bullish_directional'] = $current['di_plus'] > $current['di_minus'];
        $momentum['bearish_directional'] = $current['di_minus'] > $current['di_plus'];

        return array_filter($momentum);
    }

    /**
     * Analyze volume patterns
     */
    private static function analyzeVolume($data, $index)
    {
        $current = $data[$index];
        $volume_analysis = [];

        $volume_analysis['above_average'] = $current['volume'] > $current['volumeMA10'];
        $volume_analysis['volume_spike'] = $current['volume'] > ($current['volumeMA5'] * self::VOLUME_SPIKE_THRESHOLD);
        $volume_analysis['obv_bullish'] = $current['obv'] > $data[$index - 1]['obv'];
        $volume_analysis['obv_bearish'] = $current['obv'] < $data[$index - 1]['obv'];

        return array_filter($volume_analysis);
    }

    // ==================== NEW BEARISH CANDLESTICK PATTERNS ====================

    private static function isHangingMan($data, $index)
    {
        // Same structure as hammer but in uptrend context
        return self::isHammer($data, $index) && self::isInUptrend($data, $index);
    }

    private static function isInvertedHammer($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $upperShadow = $candle['high'] - max($candle['open'], $candle['close']);
        $lowerShadow = min($candle['open'], $candle['close']) - $candle['low'];

        return $upperShadow >= (self::HAMMER_SHADOW_RATIO * $body) &&
            $lowerShadow <= ($body * 0.1) &&
            $body > 0;
    }

    private static function isShootingStar($data, $index)
    {
        // Same as inverted hammer but in uptrend context
        return self::isInvertedHammer($data, $index) && self::isInUptrend($data, $index);
    }

    private static function isBearishMarubozu($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $range = $candle['high'] - $candle['low'];

        return ($body / max($range, 0.001)) >= 0.95 && $candle['close'] < $candle['open'];
    }

    private static function isBullishMarubozu($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $range = $candle['high'] - $candle['low'];

        return ($body / max($range, 0.001)) >= 0.95 && $candle['close'] > $candle['open'];
    }

    private static function isBearishEngulfing($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $currentBody = abs($current['close'] - $current['open']);
        $previousBody = abs($previous['close'] - $previous['open']);

        return $current['close'] < $current['open'] && // Current is bearish
            $previous['close'] > $previous['open'] && // Previous is bullish
            $current['open'] > $previous['close'] && // Opens above previous close
            $current['close'] < $previous['open'] && // Closes below previous open
            $currentBody > ($previousBody * self::ENGULFING_MIN_RATIO);
    }

    private static function isDarkCloudCover($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $previousMidpoint = ($previous['open'] + $previous['close']) / 2;

        return $previous['close'] > $previous['open'] && // Previous is bullish
            $current['close'] < $current['open'] && // Current is bearish
            $current['open'] > $previous['close'] && // Opens above previous close
            $current['close'] < $previousMidpoint && // Closes below previous midpoint
            $current['close'] > $previous['open']; // But above previous open
    }

    private static function isTweezerTop($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $highDiff = abs($current['high'] - $previous['high']);
        $avgRange = (($current['high'] - $current['low']) + ($previous['high'] - $previous['low'])) / 2;

        return $highDiff <= ($avgRange * 0.02) && // Highs are very close
            $previous['close'] > $previous['open'] && // Previous is bullish
            $current['close'] < $current['open']; // Current is bearish
    }

    private static function isHaramiBullish($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        return $previous['close'] < $previous['open'] && // Previous is bearish
            $current['close'] > $current['open'] && // Current is bullish
            $current['open'] > $previous['close'] && // Current opens above previous close
            $current['close'] < $previous['open'] && // Current closes below previous open
            abs($current['close'] - $current['open']) < abs($previous['close'] - $previous['open']); // Current body smaller
    }

    private static function isHaramiBearish($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        return $previous['close'] > $previous['open'] && // Previous is bullish
            $current['close'] < $current['open'] && // Current is bearish
            $current['open'] < $previous['close'] && // Current opens below previous close
            $current['close'] > $previous['open'] && // Current closes above previous open
            abs($current['close'] - $current['open']) < abs($previous['close'] - $previous['open']); // Current body smaller
    }

    private static function isEveningStar($data, $index)
    {
        if ($index < 2) return false;

        $first = $data[$index - 2];
        $second = $data[$index - 1];
        $third = $data[$index];

        $firstBody = abs($first['close'] - $first['open']);
        $secondBody = abs($second['close'] - $second['open']);
        $thirdBody = abs($third['close'] - $third['open']);

        return $first['close'] > $first['open'] && // First is bullish
            $secondBody < ($firstBody * 0.3) && // Second has small body (star)
            $third['close'] < $third['open'] && // Third is bearish
            $third['close'] < (($first['open'] + $first['close']) / 2); // Third closes below first midpoint
    }

    private static function isThreeBlackCrows($data, $index)
    {
        if ($index < 2) return false;

        for ($i = 0; $i < 3; $i++) {
            $candle = $data[$index - $i];
            if ($candle['close'] >= $candle['open']) return false; // All must be bearish
        }

        // Each candle should close lower than previous
        return $data[$index]['close'] < $data[$index - 1]['close'] &&
            $data[$index - 1]['close'] < $data[$index - 2]['close'];
    }

    private static function isBearishAbandonedBaby($data, $index)
    {
        if ($index < 2) return false;

        $first = $data[$index - 2];
        $second = $data[$index - 1];
        $third = $data[$index];

        return $first['close'] > $first['open'] && // First is bullish
            self::isDoji($data, $index - 1) && // Second is doji
            $third['close'] < $third['open'] && // Third is bearish
            $second['low'] > $first['high'] && // Gap up
            $second['low'] > $third['high']; // Gap down
    }

    // ==================== NEW BEARISH CHART PATTERNS ====================

    private static function isDescendingTriangle($data, $index)
    {
        $lookback = 15;
        $highs = [];
        $lows = [];

        for ($i = 1; $i <= $lookback; $i++) {
            $highs[] = $data[$index - $i]['high'];
            $lows[] = $data[$index - $i]['low'];
        }

        $supportLevel = min($lows);
        $supportCount = 0;
        $resistanceSlope = self::calculateSlope($highs);

        foreach ($lows as $low) {
            if (abs($low - $supportLevel) / $supportLevel < 0.005) {
                $supportCount++;
            }
        }

        return $supportCount >= 2 && $resistanceSlope < 0;
    }

    private static function isInvertedCupAndHandle($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $prices = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $prices[] = ($data[$index - $i]['high'] + $data[$index - $i]['low']) / 2;
        }

        // Find the inverted cup pattern (upside-down U-shape)
        $leftSide = array_slice($prices, 0, $lookback / 2);
        $rightSide = array_slice($prices, $lookback / 2);

        $leftLow = min($leftSide);
        $rightLow = min($rightSide);
        $cupTop = max($prices);

        $cupDepth = $cupTop - (($leftLow + $rightLow) / 2);
        $cupSymmetry = abs($leftLow - $rightLow) / (($leftLow + $rightLow) / 2);

        return $cupSymmetry < 0.05 && $cupDepth > 0;
    }

    private static function isDoubleTop($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $highs = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $highs[] = $data[$index - $i]['high'];
        }

        $mid = intdiv($lookback, 2);
        $firstHalf = array_slice($highs, 0, $mid + 1);
        $secondHalf = array_slice($highs, $mid + 1);

        $firstHigh = max($firstHalf);
        $secondHigh = max($secondHalf);

        $firstHighIndex = array_search($firstHigh, $highs);
        $secondHighIndex = array_search($secondHigh, $highs);

        if ($firstHighIndex === false || $secondHighIndex === false) return false;

        $highDiff = abs($highs[$firstHighIndex] - $highs[$secondHighIndex]);
        $avgHigh = ($highs[$firstHighIndex] + $highs[$secondHighIndex]) / 2;

        return ($highDiff / $avgHigh) < 0.02;
    }

    private static function isRisingWedge($data, $index)
    {
        $lookback = 15;
        if ($index < $lookback) return false;

        $highs = [];
        $lows = [];

        for ($i = $lookback; $i >= 0; $i--) {
            $highs[] = $data[$index - $i]['high'];
            $lows[] = $data[$index - $i]['low'];
        }

        $highSlope = self::calculateSlope($highs);
        $lowSlope = self::calculateSlope($lows);

        // Both slopes should be positive (rising) with highs rising slower
        return $highSlope > 0 && $lowSlope > 0 && $highSlope < $lowSlope;
    }

    private static function isBearishFlag($data, $index)
    {
        $lookback = 10;
        if ($index < $lookback + 5) return false;

        // Check for strong downward move before flag
        $preFlagMove = 0;
        for ($i = $lookback + 5; $i > $lookback; $i--) {
            if ($data[$index - $i]['close'] < $data[$index - $i]['open']) {
                $preFlagMove++;
            }
        }

        if ($preFlagMove < 3) return false;

        // Check for sideways/slightly rising consolidation
        $flagPrices = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $flagPrices[] = ($data[$index - $i]['high'] + $data[$index - $i]['low']) / 2;
        }

        $flagSlope = self::calculateSlope($flagPrices);
        return $flagSlope >= 0 && $flagSlope < 0.001;
    }

    private static function isHeadAndShoulders($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $highs = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $highs[] = $data[$index - $i]['high'];
        }

        // Find three peaks
        $peaks = [];
        for ($i = 1; $i < count($highs) - 1; $i++) {
            if ($highs[$i] > $highs[$i-1] && $highs[$i] > $highs[$i+1]) {
                $peaks[] = ['index' => $i, 'value' => $highs[$i]];
            }
        }

        if (count($peaks) < 3) return false;

        // Sort by value to find head (highest) and shoulders
        usort($peaks, function($a, $b) { return $b['value'] <=> $a['value']; });

        $head = $peaks[0];
        $leftShoulder = null;
        $rightShoulder = null;

        foreach (array_slice($peaks, 1) as $peak) {
            if ($peak['index'] < $head['index'] && !$leftShoulder) {
                $leftShoulder = $peak;
            } elseif ($peak['index'] > $head['index'] && !$rightShoulder) {
                $rightShoulder = $peak;
            }
        }

        if (!$leftShoulder || !$rightShoulder) return false;

        // Check if shoulders are approximately equal
        $shoulderDiff = abs($leftShoulder['value'] - $rightShoulder['value']);
        $avgShoulder = ($leftShoulder['value'] + $rightShoulder['value']) / 2;

        return ($shoulderDiff / $avgShoulder) < 0.05;
    }

    private static function isInverseHeadAndShoulders($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $lows = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $lows[] = $data[$index - $i]['low'];
        }

        // Find three troughs
        $troughs = [];
        for ($i = 1; $i < count($lows) - 1; $i++) {
            if ($lows[$i] < $lows[$i-1] && $lows[$i] < $lows[$i+1]) {
                $troughs[] = ['index' => $i, 'value' => $lows[$i]];
            }
        }

        if (count($troughs) < 3) return false;

        // Sort by value to find head (lowest) and shoulders
        usort($troughs, function($a, $b) { return $a['value'] <=> $b['value']; });

        $head = $troughs[0];
        $leftShoulder = null;
        $rightShoulder = null;

        foreach (array_slice($troughs, 1) as $trough) {
            if ($trough['index'] < $head['index'] && !$leftShoulder) {
                $leftShoulder = $trough;
            } elseif ($trough['index'] > $head['index'] && !$rightShoulder) {
                $rightShoulder = $trough;
            }
        }

        if (!$leftShoulder || !$rightShoulder) return false;

        // Check if shoulders are approximately equal
        $shoulderDiff = abs($leftShoulder['value'] - $rightShoulder['value']);
        $avgShoulder = ($leftShoulder['value'] + $rightShoulder['value']) / 2;

        return ($shoulderDiff / $avgShoulder) < 0.05;
    }

    // ==================== ADDITIONAL HELPER FUNCTIONS ====================

    private static function isInUptrend($data, $index, $period = 5)
    {
        if ($index < $period) return false;
        
        $closes = [];
        for ($i = 0; $i < $period; $i++) {
            $closes[] = $data[$index - $i]['close'];
        }
        
        $slope = self::calculateSlope($closes);
        return $slope > 0;
    }

    private static function isInDowntrend($data, $index, $period = 5)
    {
        if ($index < $period) return false;
        
        $closes = [];
        for ($i = 0; $i < $period; $i++) {
            $closes[] = $data[$index - $i]['close'];
        }
        
        $slope = self::calculateSlope($closes);
        return $slope < 0;
    }

    private static function isSupportBreakdown($data, $index, $support_levels)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        foreach ($support_levels as $support) {
            if ($previous['close'] >= $support && $current['close'] < $support) {
                return true;
            }
        }
        return false;
    }

    private static function isResistanceBreakout($data, $index, $resistance_levels)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        foreach ($resistance_levels as $resistance) {
            if ($previous['close'] <= $resistance && $current['close'] > $resistance) {
                return true;
            }
        }
        return false;
    }

    // ==================== EXISTING HELPER FUNCTIONS ====================

    private static function calculateSlope($values)
    {
        $n = count($values);
        if ($n < 2) return 0;

        $x_sum = array_sum(range(0, $n - 1));
        $y_sum = array_sum($values);
        $xy_sum = 0;
        $x_squared_sum = 0;

        for ($i = 0; $i < $n; $i++) {
            $xy_sum += $i * $values[$i];
            $x_squared_sum += $i * $i;
        }

        return ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x_squared_sum - $x_sum * $x_sum);
    }

    private static function findSupportLevels($data, $index, $lookback)
    {
        $levels = [];
        for ($i = 1; $i <= $lookback; $i++) {
            if ($index - $i - 1 >= 0 && $index - $i + 1 < count($data)) {
                $prev = $data[$index - $i - 1];
                $curr = $data[$index - $i];
                $next = $data[$index - $i + 1];

                if ($curr['low'] < $prev['low'] && $curr['low'] < $next['low']) {
                    $levels[] = $curr['low'];
                }
            }
        }
        return $levels;
    }

    private static function findResistanceLevels($data, $index, $lookback)
    {
        $levels = [];
        for ($i = 1; $i <= $lookback; $i++) {
            if ($index - $i - 1 >= 0 && $index - $i + 1 < count($data)) {
                $prev = $data[$index - $i - 1];
                $curr = $data[$index - $i];
                $next = $data[$index - $i + 1];

                if ($curr['high'] > $prev['high'] && $curr['high'] > $next['high']) {
                    $levels[] = $curr['high'];
                }
            }
        }
        return $levels;
    }

    private static function isNearLevel($price, $levels, $threshold_percent)
    {
        foreach ($levels as $level) {
            if (abs($price - $level) / $level <= ($threshold_percent / 100)) {
                return true;
            }
        }
        return false;
    }

    private static function isSupportBreakout($data, $index, $support_levels)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        foreach ($support_levels as $support) {
            if ($previous['close'] <= $support && $current['close'] > $support) {
                return true;
            }
        }
        return false;
    }

    private static function isResistanceRetest($data, $index, $resistance_levels)
    {
        $current = $data[$index];

        foreach ($resistance_levels as $resistance) {
            if (abs($current['close'] - $resistance) / $resistance <= 0.005) {
                return true;
            }
        }
        return false;
    }

    // ==================== EXISTING CANDLESTICK PATTERNS ====================

    private static function isHammer($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $lowerShadow = min($candle['open'], $candle['close']) - $candle['low'];
        $upperShadow = $candle['high'] - max($candle['open'], $candle['close']);

        return $lowerShadow >= (self::HAMMER_SHADOW_RATIO * $body) &&
            $upperShadow <= ($body * 0.1) &&
            $body > 0;
    }

    private static function isDoji($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $range = $candle['high'] - $candle['low'];

        return ($body / max($range, 0.001)) <= self::DOJI_BODY_RATIO;
    }

    private static function isSpinningTop($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $upperShadow = $candle['high'] - max($candle['open'], $candle['close']);
        $lowerShadow = min($candle['open'], $candle['close']) - $candle['low'];

        return $body > 0 && $upperShadow > $body && $lowerShadow > $body;
    }

    private static function isBullishEngulfing($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $currentBody = abs($current['close'] - $current['open']);
        $previousBody = abs($previous['close'] - $previous['open']);

        return $current['close'] > $current['open'] && // Current is bullish
            $previous['close'] < $previous['open'] && // Previous is bearish
            $current['open'] < $previous['close'] && // Opens below previous close
            $current['close'] > $previous['open'] && // Closes above previous open
            $currentBody > ($previousBody * self::ENGULFING_MIN_RATIO);
    }

    private static function isPiercingLine($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $previousMidpoint = ($previous['open'] + $previous['close']) / 2;

        return $previous['close'] < $previous['open'] && // Previous is bearish
            $current['close'] > $current['open'] && // Current is bullish
            $current['open'] < $previous['close'] && // Opens below previous close
            $current['close'] > $previousMidpoint && // Closes above previous midpoint
            $current['close'] < $previous['open']; // But below previous open
    }

    private static function isTweezerBottom($data, $index)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];

        $lowDiff = abs($current['low'] - $previous['low']);
        $avgRange = (($current['high'] - $current['low']) + ($previous['high'] - $previous['low'])) / 2;

        return $lowDiff <= ($avgRange * 0.02) && // Lows are very close
            $previous['close'] < $previous['open'] && // Previous is bearish
            $current['close'] > $current['open']; // Current is bullish
    }

    private static function isMorningStar($data, $index)
    {
        if ($index < 2) return false;

        $first = $data[$index - 2];
        $second = $data[$index - 1];
        $third = $data[$index];

        $firstBody = abs($first['close'] - $first['open']);
        $secondBody = abs($second['close'] - $second['open']);
        $thirdBody = abs($third['close'] - $third['open']);

        return $first['close'] < $first['open'] && // First is bearish
            $secondBody < ($firstBody * 0.3) && // Second has small body (star)
            $third['close'] > $third['open'] && // Third is bullish
            $third['close'] > (($first['open'] + $first['close']) / 2); // Third closes above first midpoint
    }

    private static function isThreeWhiteSoldiers($data, $index)
    {
        if ($index < 2) return false;

        for ($i = 0; $i < 3; $i++) {
            $candle = $data[$index - $i];
            if ($candle['close'] <= $candle['open']) return false; // All must be bullish
        }

        // Each candle should close higher than previous
        return $data[$index]['close'] > $data[$index - 1]['close'] &&
            $data[$index - 1]['close'] > $data[$index - 2]['close'];
    }

    private static function isBullishAbandonedBaby($data, $index)
    {
        if ($index < 2) return false;

        $first = $data[$index - 2];
        $second = $data[$index - 1];
        $third = $data[$index];

        return $first['close'] < $first['open'] && // First is bearish
            self::isDoji($data, $index - 1) && // Second is doji
            $third['close'] > $third['open'] && // Third is bullish
            $second['high'] < $first['low'] && // Gap down
            $second['high'] < $third['low']; // Gap up
    }

    // ==================== EXISTING CHART PATTERNS ====================

    private static function isAscendingTriangle($data, $index)
    {
        // Look for resistance level and ascending support
        $lookback = 15;
        $highs = [];
        $lows = [];

        for ($i = 1; $i <= $lookback; $i++) {
            $highs[] = $data[$index - $i]['high'];
            $lows[] = $data[$index - $i]['low'];
        }

        $resistanceLevel = max($highs);
        $resistanceCount = 0;
        $supportSlope = self::calculateSlope($lows);

        foreach ($highs as $high) {
            if (abs($high - $resistanceLevel) / $resistanceLevel < 0.005) {
                $resistanceCount++;
            }
        }

        return $resistanceCount >= 2 && $supportSlope > 0;
    }

    private static function isCupAndHandle($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $prices = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $prices[] = ($data[$index - $i]['high'] + $data[$index - $i]['low']) / 2;
        }

        // Find the cup pattern (U-shape)
        $leftSide = array_slice($prices, 0, $lookback / 2);
        $rightSide = array_slice($prices, $lookback / 2);

        $leftHigh = max($leftSide);
        $rightHigh = max($rightSide);
        $cupBottom = min($prices);

        $cupDepth = (($leftHigh + $rightHigh) / 2) - $cupBottom;
        $cupSymmetry = abs($leftHigh - $rightHigh) / (($leftHigh + $rightHigh) / 2);

        return $cupSymmetry < 0.05 && $cupDepth > 0; // Symmetric cup with reasonable depth
    }

    private static function isDoubleBottom($data, $index)
    {
        $lookback = 20;
        if ($index < $lookback) return false;

        $lows = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $lows[] = $data[$index - $i]['low'];
        }

        $mid = intdiv($lookback, 2);

        $firstHalf = array_slice($lows, 0, $mid + 1);
        $secondHalf = array_slice($lows, $mid + 1);

        $firstLow = min($firstHalf);
        $secondLow = min($secondHalf);

        $firstLowIndex = array_search($firstLow, $lows);
        $secondLowIndex = array_search($secondLow, $lows);

        // Sanity check
        if ($firstLowIndex === false || $secondLowIndex === false) return false;

        $lowDiff = abs($lows[$firstLowIndex] - $lows[$secondLowIndex]);
        $avgLow = ($lows[$firstLowIndex] + $lows[$secondLowIndex]) / 2;

        return ($lowDiff / $avgLow) < 0.02; // Lows are within 2% of each other
    }

    private static function isFallingWedge($data, $index)
    {
        $lookback = 15;
        if ($index < $lookback) return false;

        $highs = [];
        $lows = [];

        for ($i = $lookback; $i >= 0; $i--) {
            $highs[] = $data[$index - $i]['high'];
            $lows[] = $data[$index - $i]['low'];
        }

        $highSlope = self::calculateSlope($highs);
        $lowSlope = self::calculateSlope($lows);

        // Both slopes should be negative (declining) with lows declining faster
        return $highSlope < 0 && $lowSlope < 0 && $lowSlope < $highSlope;
    }

    private static function isBullishFlag($data, $index)
    {
        $lookback = 10;
        if ($index < $lookback + 5) return false;

        // Check for strong upward move before flag
        $preFlagMove = 0;
        for ($i = $lookback + 5; $i > $lookback; $i--) {
            if ($data[$index - $i]['close'] > $data[$index - $i]['open']) {
                $preFlagMove++;
            }
        }

        if ($preFlagMove < 3) return false; // Need strong prior move

        // Check for sideways/slightly declining consolidation
        $flagPrices = [];
        for ($i = $lookback; $i >= 0; $i--) {
            $flagPrices[] = ($data[$index - $i]['high'] + $data[$index - $i]['low']) / 2;
        }

        $flagSlope = self::calculateSlope($flagPrices);
        return $flagSlope <= 0 && $flagSlope > -0.001; // Slightly declining or sideways
    }

    /**
     * Calculate signal strength for LONG or SHORT entry
     */
    private static function calculateSignalStrength($patterns, $data, $index, $direction)
    {
        $score = 0;
        $maxScore = 0;
        $reasons = [];
        $current = $data[$index];

        if ($direction === 'LONG') {
            // Bullish Candlestick patterns scoring
            $bullishCandlePatterns = [
                'hammer' => 15,
                'bullish_engulfing' => 20,
                'piercing_line' => 15,
                'morning_star' => 25,
                'three_white_soldiers' => 20,
                'tweezer_bottom' => 10,
                'bullish_marubozu' => 12,
                'inverted_hammer' => 10,
                'harami_bullish' => 12,
                'bullish_abandoned_baby' => 25
            ];

            foreach ($bullishCandlePatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['candlestick_patterns'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }

            // Bullish Chart patterns scoring
            $bullishChartPatterns = [
                'ascending_triangle' => 20,
                'cup_and_handle' => 25,
                'double_bottom' => 20,
                'falling_wedge' => 15,
                'bullish_flag' => 15,
                'inverse_head_and_shoulders' => 25
            ];

            foreach ($bullishChartPatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['chart_patterns'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }

            // Support/Resistance for LONG
            $srPatterns = [
                'support_breakout' => 20,
                'near_support' => 10,
                'resistance_retest' => -10
            ];

            foreach ($srPatterns as $pattern => $points) {
                if ($points > 0) $maxScore += $points;
                if (isset($patterns['support_resistance'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " ({$points})";
                }
            }

            // Momentum for LONG
            $momentumPatterns = [
                'rsi_oversold_recovery' => 15,
                'rsi_bullish' => 10,
                'macd_bullish_cross' => 15,
                'macd_bullish_momentum' => 5,
                'stoch_oversold_recovery' => 10,
                'strong_trend' => 10,
                'bullish_directional' => 5
            ];

            foreach ($momentumPatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['momentum'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }

        } else { // SHORT direction
            // Bearish Candlestick patterns scoring
            $bearishCandlePatterns = [
                'hanging_man' => 15,
                'shooting_star' => 15,
                'bearish_engulfing' => 20,
                'dark_cloud_cover' => 15,
                'evening_star' => 25,
                'three_black_crows' => 20,
                'tweezer_top' => 10,
                'bearish_marubozu' => 12,
                'harami_bearish' => 12,
                'bearish_abandoned_baby' => 25
            ];

            foreach ($bearishCandlePatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['candlestick_patterns'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }

            // Bearish Chart patterns scoring
            $bearishChartPatterns = [
                'descending_triangle' => 20,
                'inverted_cup_and_handle' => 25,
                'double_top' => 20,
                'rising_wedge' => 15,
                'bearish_flag' => 15,
                'head_and_shoulders' => 25
            ];

            foreach ($bearishChartPatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['chart_patterns'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }

            // Support/Resistance for SHORT
            $srPatterns = [
                'support_breakdown' => 20,
                'near_resistance' => 10,
                'resistance_breakout' => -10
            ];

            foreach ($srPatterns as $pattern => $points) {
                if ($points > 0) $maxScore += $points;
                if (isset($patterns['support_resistance'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " ({$points})";
                }
            }

            // Momentum for SHORT
            $momentumPatterns = [
                'rsi_overbought_decline' => 15,
                'rsi_bearish' => 10,
                'macd_bearish_cross' => 15,
                'macd_bearish_momentum' => 5,
                'stoch_overbought_decline' => 10,
                'strong_trend' => 10,
                'bearish_directional' => 5
            ];

            foreach ($momentumPatterns as $pattern => $points) {
                $maxScore += $points;
                if (isset($patterns['momentum'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " (+{$points})";
                }
            }
        }

        // Volume scoring (same for both directions)
        $volumePatterns = [
            'volume_spike' => 10,
            'above_average' => 5,
            'obv_bullish' => ($direction === 'LONG') ? 5 : 0,
            'obv_bearish' => ($direction === 'SHORT') ? 5 : 0
        ];

        foreach ($volumePatterns as $pattern => $points) {
            if ($points > 0) {
                $maxScore += $points;
                if (isset($patterns['volume_analysis'][$pattern])) {
                    $score += $points;
                    $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " volume (+{$points})";
                }
            }
        }

        $strength = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;
        
        // Enhanced confidence calculation
        $patternCount = count($reasons);
        $confidenceBonus = min(20, $patternCount * 3); // Up to 20% bonus
        $confidence = min(100, $strength + $confidenceBonus);

        // Calculate stop loss and take profit suggestions
        $atr = self::calculateATR($data, $index, 14);
        
        if ($direction === 'LONG') {
            $stopLoss = $current['close'] - ($atr * 1.5);
            $takeProfit = $current['close'] + ($atr * 2.5);
        } else {
            $stopLoss = $current['close'] + ($atr * 1.5);
            $takeProfit = $current['close'] - ($atr * 2.5);
        }

        $riskReward = abs($takeProfit - $current['close']) / abs($current['close'] - $stopLoss);

        return [
            'strength' => round($strength, 2),
            'confidence' => round($confidence, 2),
            'reasons' => $reasons,
            'stop_loss' => round($stopLoss, 4),
            'take_profit' => round($takeProfit, 4),
            'risk_reward' => round($riskReward, 2)
        ];
    }

    /**
     * Get overall recommendation when analyzing both directions
     */
    private static function getOverallRecommendation($signals)
    {
        $longStrength = $signals['LONG']['strength'] ?? 0;
        $shortStrength = $signals['SHORT']['strength'] ?? 0;
        $longConfidence = $signals['LONG']['confidence'] ?? 0;
        $shortConfidence = $signals['SHORT']['confidence'] ?? 0;

        $longScore = ($longStrength * 0.7) + ($longConfidence * 0.3);
        $shortScore = ($shortStrength * 0.7) + ($shortConfidence * 0.3);

        $diff = abs($longScore - $shortScore);

        if ($diff < 10) {
            return [
                'action' => 'HOLD',
                'reason' => 'Conflicting signals - no clear direction',
                'score_difference' => round($diff, 2)
            ];
        }

        if ($longScore > $shortScore) {
            return [
                'action' => 'LONG',
                'reason' => 'Bullish signals dominate',
                'score_difference' => round($diff, 2),
                'primary_strength' => $longStrength,
                'primary_confidence' => $longConfidence
            ];
        } else {
            return [
                'action' => 'SHORT',
                'reason' => 'Bearish signals dominate',
                'score_difference' => round($diff, 2),
                'primary_strength' => $shortStrength,
                'primary_confidence' => $shortConfidence
            ];
        }
    }

    private static function calculateATR($data, $index, $period)
    {
        $trueRanges = [];

        for ($i = 1; $i <= $period && ($index - $i) >= 0; $i++) {
            $current = $data[$index - $i + 1];
            $previous = $data[$index - $i];

            $tr = max(
                $current['high'] - $current['low'],
                abs($current['high'] - $previous['close']),
                abs($current['low'] - $previous['close'])
            );

            $trueRanges[] = $tr;
        }

        return count($trueRanges) > 0 ? array_sum($trueRanges) / count($trueRanges) : 0;
    }
}