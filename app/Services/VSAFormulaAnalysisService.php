<?php

namespace App\Services;

/**
 * VSA Formula Analysis Service
 * Provides Volume Spread Analysis and Break of Structure detection
 */
class VSAFormulaAnalysisService
{
    /**
     * Default configuration for analysis
     */
    private const DEFAULT_LOOKBACK = 5;
    private const DEFAULT_MIN_SWING_STRENGTH = 3;
    private const CANDLE_STRENGTH_THRESHOLD = 0.6;

    /**
     * Detects break of structure (BOS) in market data
     * 
     * @param array $data - Array of candle data
     * @param int $index - Current candle index
     * @param int $lookback - Number of candles to look back
     * @param int $minSwingStrength - Minimum strength for swing detection
     * @return string|null - 'BULLISH_BOS', 'BEARISH_BOS', or null
     */
    public function detectBreakOfStructure(array $data, int $index, int $lookback = self::DEFAULT_LOOKBACK, int $minSwingStrength = self::DEFAULT_MIN_SWING_STRENGTH): ?string
    {
        // Need enough data to analyze
        if ($index < $lookback * 2 || $index >= count($data) - 1) {
            return null;
        }

        $currentCandle = $data[$index];

        // Find recent swing highs and lows
        $swingHighs = $this->findSwingHighs($data, $index, $lookback, $minSwingStrength);
        $swingLows = $this->findSwingLows($data, $index, $lookback, $minSwingStrength);

        // Need at least 2 swing points to determine structure
        if (count($swingHighs) < 2 || count($swingLows) < 2) {
            return null;
        }

        // Check for bullish break of structure
        if ($this->checkBullishBOS($swingLows, $currentCandle)) {
            return 'BULLISH_BOS';
        }

        // Check for bearish break of structure
        if ($this->checkBearishBOS($swingHighs, $currentCandle)) {
            return 'BEARISH_BOS';
        }

        return null;
    }

    /**
     * Enhanced market structure analysis with detailed information
     * 
     * @param array $data - Array of candle data
     * @param int $index - Current candle index
     * @param int $lookback - Number of candles to look back
     * @param int $minSwingStrength - Minimum strength for swing detection
     * @return array - Detailed market structure analysis
     */
    public function detectMarketStructure(array $data, int $index, int $lookback = 10, int $minSwingStrength = self::DEFAULT_MIN_SWING_STRENGTH): array
    {
        if ($index < $lookback * 2 || $index >= count($data) - 1) {
            return [
                'structure' => null,
                'confidence' => 0,
                'details' => 'Insufficient data'
            ];
        }

        $swingHighs = $this->findSwingHighs($data, $index, $lookback, $minSwingStrength);
        $swingLows = $this->findSwingLows($data, $index, $lookback, $minSwingStrength);

        if (count($swingHighs) < 2 || count($swingLows) < 2) {
            return [
                'structure' => null,
                'confidence' => 0,
                'details' => 'Insufficient swing points'
            ];
        }

        // Analyze the pattern of highs and lows
        $higherHighs = 0;
        $lowerHighs = 0;
        $higherLows = 0;
        $lowerLows = 0;

        // Compare recent swing highs
        for ($i = 0; $i < min(3, count($swingHighs) - 1); $i++) {
            if ($swingHighs[$i]['price'] > $swingHighs[$i + 1]['price']) {
                $higherHighs++;
            } else {
                $lowerHighs++;
            }
        }

        // Compare recent swing lows
        for ($i = 0; $i < min(3, count($swingLows) - 1); $i++) {
            if ($swingLows[$i]['price'] > $swingLows[$i + 1]['price']) {
                $higherLows++;
            } else {
                $lowerLows++;
            }
        }

        // Determine trend and confidence
        $bullishSignals = $higherHighs + $higherLows;
        $bearishSignals = $lowerHighs + $lowerLows;
        $totalSignals = $bullishSignals + $bearishSignals;

        if ($totalSignals == 0) {
            return [
                'structure' => null,
                'confidence' => 0,
                'details' => 'No clear structure'
            ];
        }

        $confidence = max($bullishSignals, $bearishSignals) / $totalSignals * 100;

        if ($bullishSignals > $bearishSignals) {
            return [
                'structure' => 'BULLISH_BOS',
                'confidence' => round($confidence, 2),
                'details' => "Higher highs: {$higherHighs}, Higher lows: {$higherLows}",
                'swing_highs' => $swingHighs,
                'swing_lows' => $swingLows
            ];
        } elseif ($bearishSignals > $bullishSignals) {
            return [
                'structure' => 'BEARISH_BOS',
                'confidence' => round($confidence, 2),
                'details' => "Lower highs: {$lowerHighs}, Lower lows: {$lowerLows}",
                'swing_highs' => $swingHighs,
                'swing_lows' => $swingLows
            ];
        }

        return [
            'structure' => null,
            'confidence' => 0,
            'details' => 'Neutral/Sideways market',
            'swing_highs' => $swingHighs,
            'swing_lows' => $swingLows
        ];
    }

    /**
     * Get swing analysis for visualization or debugging
     * 
     * @param array $data - Array of candle data
     * @param int $index - Current candle index
     * @param int $lookback - Number of candles to look back
     * @param int $minSwingStrength - Minimum strength for swing detection
     * @return array - Array containing swing highs and lows
     */
    public function getSwingAnalysis(array $data, int $index, int $lookback = self::DEFAULT_LOOKBACK, int $minSwingStrength = self::DEFAULT_MIN_SWING_STRENGTH): array
    {
        return [
            'swing_highs' => $this->findSwingHighs($data, $index, $lookback, $minSwingStrength),
            'swing_lows' => $this->findSwingLows($data, $index, $lookback, $minSwingStrength),
            'lookback_period' => $lookback,
            'swing_strength' => $minSwingStrength
        ];
    }

    /**
     * Find swing highs in the data
     * 
     * @param array $data - Array of candle data
     * @param int $currentIndex - Current candle index
     * @param int $lookback - Number of candles to look back
     * @param int $strength - Minimum strength for swing detection
     * @return array - Array of swing high points
     */
    private function findSwingHighs(array $data, int $currentIndex, int $lookback, int $strength): array
    {
        $swingHighs = [];
        $startIndex = max(0, $currentIndex - $lookback);

        for ($i = $startIndex + $strength; $i <= $currentIndex - $strength; $i++) {
            $isSwingHigh = true;
            $currentHigh = $data[$i]['high'];

            // Check if current candle high is higher than surrounding candles
            for ($j = $i - $strength; $j <= $i + $strength; $j++) {
                if ($j != $i && isset($data[$j]) && $data[$j]['high'] >= $currentHigh) {
                    $isSwingHigh = false;
                    break;
                }
            }

            if ($isSwingHigh) {
                $swingHighs[] = [
                    'index' => $i,
                    'price' => $currentHigh,
                    'timestamp' => $data[$i]['timestamp']
                ];
            }
        }

        // Sort by index (most recent first)
        usort($swingHighs, function ($a, $b) {
            return $b['index'] - $a['index'];
        });

        return $swingHighs;
    }

    /**
     * Find swing lows in the data
     * 
     * @param array $data - Array of candle data
     * @param int $currentIndex - Current candle index
     * @param int $lookback - Number of candles to look back
     * @param int $strength - Minimum strength for swing detection
     * @return array - Array of swing low points
     */
    private function findSwingLows(array $data, int $currentIndex, int $lookback, int $strength): array
    {
        $swingLows = [];
        $startIndex = max(0, $currentIndex - $lookback);

        for ($i = $startIndex + $strength; $i <= $currentIndex - $strength; $i++) {
            $isSwingLow = true;
            $currentLow = $data[$i]['low'];

            // Check if current candle low is lower than surrounding candles
            for ($j = $i - $strength; $j <= $i + $strength; $j++) {
                if ($j != $i && isset($data[$j]) && $data[$j]['low'] <= $currentLow) {
                    $isSwingLow = false;
                    break;
                }
            }

            if ($isSwingLow) {
                $swingLows[] = [
                    'index' => $i,
                    'price' => $currentLow,
                    'timestamp' => $data[$i]['timestamp']
                ];
            }
        }

        // Sort by index (most recent first)
        usort($swingLows, function ($a, $b) {
            return $b['index'] - $a['index'];
        });

        return $swingLows;
    }

    /**
     * Check for bullish break of structure
     * 
     * @param array $swingLows - Array of swing low points
     * @param array $currentCandle - Current candle data
     * @return bool - True if bullish BOS detected
     */
    private function checkBullishBOS(array $swingLows, array $currentCandle): bool
    {
        if (count($swingLows) < 2) {
            return false;
        }

        // Get the most recent two swing lows
        $recentLow = $swingLows[0];
        $previousLow = $swingLows[1];

        // For bullish BOS, we need:
        // 1. Recent low should be higher than previous low (higher lows)
        // 2. Current price should show strength

        $isHigherLow = $recentLow['price'] > $previousLow['price'];

        if (!$isHigherLow) {
            return false;
        }

        // Check if current candle is showing strength (closing in upper portion)
        $candleRange = $currentCandle['high'] - $currentCandle['low'];
        if ($candleRange == 0) {
            return false;
        }

        $candleStrength = ($currentCandle['close'] - $currentCandle['low']) / $candleRange;

        return $candleStrength > self::CANDLE_STRENGTH_THRESHOLD;
    }

    /**
     * Check for bearish break of structure
     * 
     * @param array $swingHighs - Array of swing high points
     * @param array $currentCandle - Current candle data
     * @return bool - True if bearish BOS detected
     */
    private function checkBearishBOS(array $swingHighs, array $currentCandle): bool
    {
        if (count($swingHighs) < 2) {
            return false;
        }

        // Get the most recent two swing highs
        $recentHigh = $swingHighs[0];
        $previousHigh = $swingHighs[1];

        // For bearish BOS, we need:
        // 1. Recent high should be lower than previous high (lower highs)
        // 2. Current price should show weakness

        $isLowerHigh = $recentHigh['price'] < $previousHigh['price'];

        if (!$isLowerHigh) {
            return false;
        }

        // Check if current candle is showing weakness (closing in lower portion)
        $candleRange = $currentCandle['high'] - $currentCandle['low'];
        if ($candleRange == 0) {
            return false;
        }

        $candleWeakness = ($currentCandle['high'] - $currentCandle['close']) / $candleRange;

        return $candleWeakness > self::CANDLE_STRENGTH_THRESHOLD;
    }

    /**
     * Calculate candle strength/weakness ratio
     * 
     * @param array $candle - Candle data
     * @return float - Strength ratio (0-1)
     */
    public function calculateCandleStrength(array $candle): float
    {
        $candleRange = $candle['high'] - $candle['low'];
        if ($candleRange == 0) {
            return 0.5; // Neutral if no range
        }

        return ($candle['close'] - $candle['low']) / $candleRange;
    }

    /**
     * Determine if market is in a trending or ranging state
     * 
     * @param array $data - Array of candle data
     * @param int $index - Current candle index
     * @param int $lookback - Number of candles to analyze
     * @return array - Market state analysis
     */
    public function getMarketState(array $data, int $index, int $lookback = 20): array
    {
        if ($index < $lookback || $index >= count($data)) {
            return [
                'state' => 'UNKNOWN',
                'confidence' => 0,
                'details' => 'Insufficient data'
            ];
        }

        $structure = $this->detectMarketStructure($data, $index, $lookback);

        if ($structure['confidence'] > 70) {
            return [
                'state' => 'TRENDING',
                'direction' => $structure['structure'],
                'confidence' => $structure['confidence'],
                'details' => 'Strong trending market - ' . $structure['details']
            ];
        } elseif ($structure['confidence'] > 40) {
            return [
                'state' => 'WEAK_TREND',
                'direction' => $structure['structure'],
                'confidence' => $structure['confidence'],
                'details' => 'Weak trending market - ' . $structure['details']
            ];
        } else {
            return [
                'state' => 'RANGING',
                'direction' => null,
                'confidence' => 100 - $structure['confidence'],
                'details' => 'Sideways/Ranging market'
            ];
        }
    }
}
