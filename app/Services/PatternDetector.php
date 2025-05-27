<?php

namespace App\Services;

class PatternDetector
{
    // Configuration constants
    const MIN_BODY_RATIO = 0.6; // For strong body candles
    const DOJI_BODY_RATIO = 0.1; // For doji identification
    const HAMMER_SHADOW_RATIO = 2.0; // Lower shadow should be 2x body size
    const ENGULFING_MIN_RATIO = 1.2; // Engulfing body should be 20% larger
    const VOLUME_SPIKE_THRESHOLD = 1.5; // Volume should be 50% above average
    const RSI_OVERSOLD = 30;
    const RSI_OVERBOUGHT = 70;

    /**
     * Main function to analyze patterns and return LONG signal strength
     */
    public static function analyzeLongEntry($data, $index, $supportResistance = null)
    {
        if ($index < 20) return null; // Need enough history

        $patterns = [
            'candlestick_patterns' => self::detectCandlestickPatterns($data, $index),
            'chart_patterns' => self::detectChartPatterns($data, $index),
            'support_resistance' => self::analyzeSupportResistance($data, $index, $supportResistance),
            'momentum' => self::analyzeMomentum($data, $index),
            'volume_analysis' => self::analyzeVolume($data, $index)
        ];

        $signal = self::calculateLongSignalStrength($patterns, $data, $index);

        return [
            'signal_strength' => $signal['strength'],
            'confidence' => $signal['confidence'],
            'patterns_detected' => $patterns,
            'entry_reason' => $signal['reasons'],
            'stop_loss_suggestion' => $signal['stop_loss'],
            'take_profit_suggestion' => $signal['take_profit']
        ];
    }

    /**
     * Detect various candlestick patterns
     */
    private static function detectCandlestickPatterns($data, $index)
    {
        $patterns = [];

        // Single candle patterns
        $patterns['hammer'] = self::isHammer($data, $index);
        $patterns['doji'] = self::isDoji($data, $index);
        $patterns['spinning_top'] = self::isSpinningTop($data, $index);
        $patterns['marubozu'] = self::isMarubozu($data, $index);

        // Two candle patterns
        if ($index >= 1) {
            $patterns['bullish_engulfing'] = self::isBullishEngulfing($data, $index);
            $patterns['piercing_line'] = self::isPiercingLine($data, $index);
            $patterns['tweezer_bottom'] = self::isTweezerBottom($data, $index);
        }

        // Three candle patterns
        if ($index >= 2) {
            $patterns['morning_star'] = self::isMorningStar($data, $index);
            $patterns['three_white_soldiers'] = self::isThreeWhiteSoldiers($data, $index);
            $patterns['bullish_abandoned_baby'] = self::isBullishAbandonedBaby($data, $index);
        }

        return array_filter($patterns); // Remove false values
    }

    /**
     * Detect chart patterns like head and shoulders, triangles, etc.
     */
    private static function detectChartPatterns($data, $index)
    {
        $patterns = [];

        if ($index >= 20) {
            $patterns['ascending_triangle'] = self::isAscendingTriangle($data, $index);
            $patterns['cup_and_handle'] = self::isCupAndHandle($data, $index);
            $patterns['double_bottom'] = self::isDoubleBottom($data, $index);
            $patterns['falling_wedge'] = self::isFallingWedge($data, $index);
            $patterns['bullish_flag'] = self::isBullishFlag($data, $index);
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

        // Find recent support levels
        $support_levels = self::findSupportLevels($data, $index, 20);
        $resistance_levels = self::findResistanceLevels($data, $index, 20);

        $analysis['near_support'] = self::isNearLevel($current['close'], $support_levels, 0.5);
        $analysis['near_resistance'] = self::isNearLevel($current['close'], $resistance_levels, 0.5);
        $analysis['support_breakout'] = self::isSupportBreakout($data, $index, $support_levels);
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
        $momentum['rsi_bullish'] = $current['rsi6'] > 50 && $current['rsi6'] < 70;

        // MACD analysis
        $momentum['macd_bullish_cross'] = $current['histogram'] > 0 &&
            $data[$index - 1]['histogram'] <= 0;
        $momentum['macd_momentum'] = $current['histogram'] > $data[$index - 1]['histogram'];

        // Stochastic analysis
        $momentum['stoch_oversold_recovery'] = $current['stoch_rsi'] > 0.2 &&
            $data[$index - 1]['stoch_rsi'] <= 0.2;

        // ADX for trend strength
        $momentum['strong_trend'] = $current['adx'] > 25;
        $momentum['bullish_directional'] = $current['di_plus'] > $current['di_minus'];

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

        return array_filter($volume_analysis);
    }

    // ==================== CANDLESTICK PATTERN IMPLEMENTATIONS ====================

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

        return ($body / $range) <= self::DOJI_BODY_RATIO;
    }

    private static function isSpinningTop($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $upperShadow = $candle['high'] - max($candle['open'], $candle['close']);
        $lowerShadow = min($candle['open'], $candle['close']) - $candle['low'];

        return $body > 0 && $upperShadow > $body && $lowerShadow > $body;
    }

    private static function isMarubozu($data, $index)
    {
        $candle = $data[$index];
        $body = abs($candle['close'] - $candle['open']);
        $range = $candle['high'] - $candle['low'];

        return ($body / $range) >= 0.95 && $candle['close'] > $candle['open'];
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

    // ==================== CHART PATTERN IMPLEMENTATIONS ====================

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

        // Find two significant lows
        $firstLowIndex = array_search(min(array_slice($lows, 0, $lookback / 2)), $lows);
        $secondLowIndex = array_search(min(array_slice($lows, $lookback / 2)), $lows) + $lookback / 2;

        if ($firstLowIndex !== false && $secondLowIndex !== false) {
            $lowDiff = abs($lows[$firstLowIndex] - $lows[$secondLowIndex]);
            $avgLow = ($lows[$firstLowIndex] + $lows[$secondLowIndex]) / 2;

            return ($lowDiff / $avgLow) < 0.02; // Lows are within 2% of each other
        }

        return false;
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

    // ==================== HELPER FUNCTIONS ====================

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

    /**
     * Calculate overall signal strength for LONG entry
     */
    private static function calculateLongSignalStrength($patterns, $data, $index)
    {
        $score = 0;
        $maxScore = 0;
        $reasons = [];
        $current = $data[$index];

        // Candlestick patterns scoring
        $bullishCandlePatterns = [
            'hammer' => 15,
            'bullish_engulfing' => 20,
            'piercing_line' => 15,
            'morning_star' => 25,
            'three_white_soldiers' => 20,
            'tweezer_bottom' => 10
        ];

        foreach ($bullishCandlePatterns as $pattern => $points) {
            $maxScore += $points;
            if (isset($patterns['candlestick_patterns'][$pattern])) {
                $score += $points;
                $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " detected (+{$points})";
            }
        }

        // Chart patterns scoring
        $bullishChartPatterns = [
            'ascending_triangle' => 20,
            'cup_and_handle' => 25,
            'double_bottom' => 20,
            'falling_wedge' => 15,
            'bullish_flag' => 15
        ];

        foreach ($bullishChartPatterns as $pattern => $points) {
            $maxScore += $points;
            if (isset($patterns['chart_patterns'][$pattern])) {
                $score += $points;
                $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " pattern (+{$points})";
            }
        }

        // Support/Resistance scoring
        $srPatterns = [
            'support_breakout' => 20,
            'near_support' => 10,
            'resistance_retest' => -10 // Negative for LONG
        ];

        foreach ($srPatterns as $pattern => $points) {
            if ($points > 0) $maxScore += $points;
            if (isset($patterns['support_resistance'][$pattern])) {
                $score += $points;
                $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " ({$points})";
            }
        }

        // Momentum scoring
        $momentumPatterns = [
            'rsi_oversold_recovery' => 15,
            'rsi_bullish' => 10,
            'macd_bullish_cross' => 15,
            'macd_momentum' => 5,
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

        // Volume scoring
        $volumePatterns = [
            'volume_spike' => 10,
            'above_average' => 5,
            'obv_bullish' => 5
        ];

        foreach ($volumePatterns as $pattern => $points) {
            $maxScore += $points;
            if (isset($patterns['volume_analysis'][$pattern])) {
                $score += $points;
                $reasons[] = ucwords(str_replace('_', ' ', $pattern)) . " volume (+{$points})";
            }
        }

        $strength = $maxScore > 0 ? ($score / $maxScore) * 100 : 0;
        $confidence = min(100, $strength + (count($reasons) * 2));

        // Calculate stop loss and take profit suggestions
        $atr = self::calculateATR($data, $index, 14);
        $stopLoss = $current['close'] - ($atr * 1.5);
        $takeProfit = $current['close'] + ($atr * 2.5);

        return [
            'strength' => round($strength, 2),
            'confidence' => round($confidence, 2),
            'reasons' => $reasons,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit
        ];
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
