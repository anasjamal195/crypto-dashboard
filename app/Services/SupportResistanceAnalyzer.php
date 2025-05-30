<?php

namespace App\Services;

class SupportResistanceAnalyzer
{
    private $data;
    private $currentIndex;
    private $lookbackPeriod;
    private $minTouchPoints;
    private $priceThreshold;
    
    public function __construct($data, $currentIndex, $lookbackPeriod = 100, $minTouchPoints = 2, $priceThreshold = 0.0015)
    {
        $this->data = $data;
        $this->currentIndex = $currentIndex;
        $this->lookbackPeriod = $lookbackPeriod;
        $this->minTouchPoints = $minTouchPoints;
        $this->priceThreshold = $priceThreshold; // 0.15% price threshold for level validation
    }
    
    /**
     * Main analysis function that returns comprehensive support/resistance data
     */
    public function analyze()
    {
        $currentPrice = $this->data[$this->currentIndex]['close'];
        
        // Step 1: Identify potential pivot points
        $pivotPoints = $this->findPivotPoints();
        
        // Step 2: Group similar price levels into support/resistance zones
        $srLevels = $this->identifySupportResistanceLevels($pivotPoints);
        
        // Step 3: Validate levels with volume and touch count
        $validatedLevels = $this->validateLevels($srLevels);
        
        // Step 4: Classify levels and determine strength
        $classifiedLevels = $this->classifyLevels($validatedLevels);
        
        // Step 5: Identify recent breakouts
        $breakouts = $this->identifyBreakouts($classifiedLevels);
        
        // Step 6: Generate trading signals
        $tradingSignals = $this->generateTradingSignals($classifiedLevels, $currentPrice);
        
        // Step 7: Calculate confidence scores
        $confidenceScores = $this->calculateConfidenceScores($classifiedLevels, $tradingSignals);
        
        return [
            'current_price' => $currentPrice,
            'timestamp' => $this->data[$this->currentIndex]['timestampReadable'],
            'support_resistance_levels' => $classifiedLevels,
            'recent_breakouts' => $breakouts,
            'trading_signals' => $tradingSignals,
            'confidence_analysis' => $confidenceScores,
            'market_structure' => $this->analyzeMarketStructure($classifiedLevels, $currentPrice),
            'sr_indexes' => $this->getSRIndexes($classifiedLevels),
            'analysis_summary' => $this->generateAnalysisSummary($classifiedLevels, $tradingSignals, $confidenceScores)
        ];
    }
    
    /**
     * Find pivot highs and lows in the data
     */
    private function findPivotPoints($leftBars = 5, $rightBars = 5)
    {
        $pivots = [];
        $startIndex = max($leftBars, $this->currentIndex - $this->lookbackPeriod);
        $endIndex = min($this->currentIndex - $rightBars, count($this->data) - $rightBars - 1);
        
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            // Check for pivot high
            $isPivotHigh = true;
            $currentHigh = $this->data[$i]['high'];
            
            // Check left side
            for ($j = $i - $leftBars; $j < $i; $j++) {
                if ($this->data[$j]['high'] >= $currentHigh) {
                    $isPivotHigh = false;
                    break;
                }
            }
            
            // Check right side
            if ($isPivotHigh) {
                for ($j = $i + 1; $j <= $i + $rightBars; $j++) {
                    if ($this->data[$j]['high'] >= $currentHigh) {
                        $isPivotHigh = false;
                        break;
                    }
                }
            }
            
            // Check for pivot low
            $isPivotLow = true;
            $currentLow = $this->data[$i]['low'];
            
            // Check left side
            for ($j = $i - $leftBars; $j < $i; $j++) {
                if ($this->data[$j]['low'] <= $currentLow) {
                    $isPivotLow = false;
                    break;
                }
            }
            
            // Check right side
            if ($isPivotLow) {
                for ($j = $i + 1; $j <= $i + $rightBars; $j++) {
                    if ($this->data[$j]['low'] <= $currentLow) {
                        $isPivotLow = false;
                        break;
                    }
                }
            }
            
            if ($isPivotHigh) {
                $pivots[] = [
                    'type' => 'resistance',
                    'price' => $currentHigh,
                    'index' => $i,
                    'timestamp' => $this->data[$i]['timestampReadable'],
                    'volume' => $this->data[$i]['volume'],
                    'strength' => $this->calculatePivotStrength($i, 'high')
                ];
            }
            
            if ($isPivotLow) {
                $pivots[] = [
                    'type' => 'support',
                    'price' => $currentLow,
                    'index' => $i,
                    'timestamp' => $this->data[$i]['timestampReadable'],
                    'volume' => $this->data[$i]['volume'],
                    'strength' => $this->calculatePivotStrength($i, 'low')
                ];
            }
        }
        
        return $pivots;
    }
    
    /**
     * Calculate the strength of a pivot point based on surrounding price action
     */
    private function calculatePivotStrength($index, $type)
    {
        $strength = 0;
        $lookback = 10;
        
        $pivotPrice = ($type === 'high') ? $this->data[$index]['high'] : $this->data[$index]['low'];
        
        // Check how many times price approached this level
        $startCheck = max(0, $index - $lookback);
        $endCheck = min(count($this->data) - 1, $index + $lookback);
        
        for ($i = $startCheck; $i <= $endCheck; $i++) {
            if ($i === $index) continue;
            
            $high = $this->data[$i]['high'];
            $low = $this->data[$i]['low'];
            
            if ($type === 'high') {
                $distance = abs($high - $pivotPrice) / $pivotPrice;
                if ($distance <= $this->priceThreshold) {
                    $strength += (1 - $distance / $this->priceThreshold);
                }
            } else {
                $distance = abs($low - $pivotPrice) / $pivotPrice;
                if ($distance <= $this->priceThreshold) {
                    $strength += (1 - $distance / $this->priceThreshold);
                }
            }
        }
        
        // Add volume factor
        $avgVolume = $this->getAverageVolume($index, 20);
        $volumeFactor = $this->data[$index]['volume'] / $avgVolume;
        $strength *= (1 + min($volumeFactor, 2) / 10);
        
        return round($strength, 2);
    }
    
    /**
     * Group similar pivot points into support/resistance levels
     */
    private function identifySupportResistanceLevels($pivotPoints)
    {
        $levels = [];
        $used = [];
        
        foreach ($pivotPoints as $i => $pivot) {
            if (isset($used[$i])) continue;
            
            $level = [
                'type' => $pivot['type'],
                'price' => $pivot['price'],
                'touches' => [$pivot],
                'avg_price' => $pivot['price'],
                'total_volume' => $pivot['volume'],
                'strength' => $pivot['strength'],
                'first_touch_index' => $pivot['index'],
                'last_touch_index' => $pivot['index']
            ];
            
            $used[$i] = true;
            
            // Find similar pivots within threshold
            foreach ($pivotPoints as $j => $otherPivot) {
                if ($i === $j || isset($used[$j]) || $pivot['type'] !== $otherPivot['type']) continue;
                
                $priceDiff = abs($pivot['price'] - $otherPivot['price']) / $pivot['price'];
                
                if ($priceDiff <= $this->priceThreshold) {
                    $level['touches'][] = $otherPivot;
                    $level['total_volume'] += $otherPivot['volume'];
                    $level['strength'] += $otherPivot['strength'];
                    $level['last_touch_index'] = max($level['last_touch_index'], $otherPivot['index']);
                    $used[$j] = true;
                }
            }
            
            // Calculate average price
            $totalPrice = 0;
            foreach ($level['touches'] as $touch) {
                $totalPrice += $touch['price'];
            }
            $level['avg_price'] = $totalPrice / count($level['touches']);
            $level['touch_count'] = count($level['touches']);
            
            if ($level['touch_count'] >= $this->minTouchPoints) {
                $levels[] = $level;
            }
        }
        
        return $levels;
    }
    
    /**
     * Validate levels based on various criteria
     */
    private function validateLevels($levels)
    {
        $validated = [];
        
        foreach ($levels as $level) {
            $validation = $this->validateLevel($level);
            
            if ($validation['is_valid']) {
                $level['validation'] = $validation;
                $level['confidence'] = $this->calculateLevelConfidence($level);
                $validated[] = $level;
            }
        }
        
        // Sort by confidence
        usort($validated, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $validated;
    }
    
    /**
     * Validate individual level
     */
    private function validateLevel($level)
    {
        $validation = [
            'is_valid' => true,
            'reasons' => [],
            'score' => 0
        ];
        
        // Touch count validation
        if ($level['touch_count'] >= 3) {
            $validation['score'] += 30;
            $validation['reasons'][] = "Strong level with {$level['touch_count']} touches";
        } elseif ($level['touch_count'] >= 2) {
            $validation['score'] += 20;
            $validation['reasons'][] = "Valid level with {$level['touch_count']} touches";
        }
        
        // Volume validation
        $avgVolume = $this->getAverageVolume($this->currentIndex, 20);
        $volumeRatio = $level['total_volume'] / ($avgVolume * $level['touch_count']);
        
        if ($volumeRatio > 1.5) {
            $validation['score'] += 25;
            $validation['reasons'][] = "High volume confirmation";
        } elseif ($volumeRatio > 1.0) {
            $validation['score'] += 15;
            $validation['reasons'][] = "Above average volume";
        }
        
        // Recent activity validation
        $barsSinceLastTouch = $this->currentIndex - $level['last_touch_index'];
        if ($barsSinceLastTouch < 20) {
            $validation['score'] += 20;
            $validation['reasons'][] = "Recent price interaction";
        } elseif ($barsSinceLastTouch < 50) {
            $validation['score'] += 10;
            $validation['reasons'][] = "Moderately recent interaction";
        }
        
        // Price significance
        $currentPrice = $this->data[$this->currentIndex]['close'];
        $distanceFromCurrent = abs($level['avg_price'] - $currentPrice) / $currentPrice;
        
        if ($distanceFromCurrent < 0.05) {  // Within 5%
            $validation['score'] += 25;
            $validation['reasons'][] = "Close to current price";
        } elseif ($distanceFromCurrent < 0.10) {  // Within 10%
            $validation['score'] += 15;
            $validation['reasons'][] = "Near current price range";
        }
        
        $validation['is_valid'] = $validation['score'] >= 40;
        
        return $validation;
    }
    
    /**
     * Calculate confidence for a level
     */
    private function calculateLevelConfidence($level)
    {
        $confidence = $level['validation']['score'];
        
        // Add technical indicator confluence
        $confluence = $this->checkTechnicalConfluence($level['avg_price']);
        $confidence += $confluence * 10;
        
        // Time-based weighting (more recent = higher confidence)
        $recency = ($this->currentIndex - $level['last_touch_index']) / $this->lookbackPeriod;
        $recencyWeight = 1 - $recency;
        $confidence *= (0.5 + $recencyWeight * 0.5);
        
        return min(100, round($confidence, 1));
    }
    
    /**
     * Check technical indicator confluence at a price level
     */
    private function checkTechnicalConfluence($price)
    {
        $confluence = 0;
        $current = $this->data[$this->currentIndex];
        $threshold = $price * 0.002; // 0.2% threshold
        
        // Moving averages
        $mas = [$current['ma7'], $current['ma14'], $current['ma25'], $current['ma99']];
        foreach ($mas as $ma) {
            if (abs($ma - $price) <= $threshold) {
                $confluence += 0.5;
            }
        }
        
        // Bollinger Bands
        if (abs($current['bb_upper'] - $price) <= $threshold || 
            abs($current['bb_lower'] - $price) <= $threshold ||
            abs($current['bb_middle'] - $price) <= $threshold) {
            $confluence += 0.5;
        }
        
        // VWAP
        if (abs($current['vwap'] - $price) <= $threshold) {
            $confluence += 0.3;
        }
        
        // Parabolic SAR
        if (abs($current['sar'] - $price) <= $threshold) {
            $confluence += 0.3;
        }
        
        return min($confluence, 2.0);
    }
    
    /**
     * Classify levels into major/minor and determine current relevance
     */
    private function classifyLevels($levels)
    {
        $currentPrice = $this->data[$this->currentIndex]['close'];
        
        foreach ($levels as &$level) {
            // Classify as major or minor
            if ($level['confidence'] >= 70 && $level['touch_count'] >= 3) {
                $level['classification'] = 'major';
            } elseif ($level['confidence'] >= 50) {
                $level['classification'] = 'minor';
            } else {
                $level['classification'] = 'weak';
            }
            
            // Determine proximity to current price
            $distance = abs($level['avg_price'] - $currentPrice) / $currentPrice;
            if ($distance <= 0.01) {
                $level['proximity'] = 'immediate';
            } elseif ($distance <= 0.03) {
                $level['proximity'] = 'near';
            } elseif ($distance <= 0.10) {
                $level['proximity'] = 'moderate';
            } else {
                $level['proximity'] = 'distant';
            }
            
            // Risk/Reward calculation
            $level['risk_reward'] = $this->calculateRiskReward($level, $currentPrice);
        }
        
        return $levels;
    }
    
    /**
     * Calculate risk/reward ratio for a level
     */
    private function calculateRiskReward($level, $currentPrice)
    {
        $levelPrice = $level['avg_price'];
        
        if ($level['type'] === 'support' && $currentPrice > $levelPrice) {
            // Long opportunity
            $risk = $currentPrice - $levelPrice;
            $nearestResistance = $this->findNearestLevel($currentPrice, 'resistance');
            $reward = $nearestResistance ? $nearestResistance['avg_price'] - $currentPrice : $risk * 2;
            
            return [
                'direction' => 'long',
                'entry' => $currentPrice,
                'stop_loss' => $levelPrice * 0.999, // Slightly below support
                'take_profit' => $nearestResistance ? $nearestResistance['avg_price'] * 0.999 : $currentPrice + $reward,
                'risk' => $risk,
                'reward' => $reward,
                'ratio' => $reward > 0 ? round($reward / $risk, 2) : 0
            ];
        } elseif ($level['type'] === 'resistance' && $currentPrice < $levelPrice) {
            // Short opportunity
            $risk = $levelPrice - $currentPrice;
            $nearestSupport = $this->findNearestLevel($currentPrice, 'support');
            $reward = $nearestSupport ? $currentPrice - $nearestSupport['avg_price'] : $risk * 2;
            
            return [
                'direction' => 'short',
                'entry' => $currentPrice,
                'stop_loss' => $levelPrice * 1.001, // Slightly above resistance
                'take_profit' => $nearestSupport ? $nearestSupport['avg_price'] * 1.001 : $currentPrice - $reward,
                'risk' => $risk,
                'reward' => $reward,
                'ratio' => $reward > 0 ? round($reward / $risk, 2) : 0
            ];
        }
        
        return null;
    }
    
    /**
     * Find nearest support or resistance level
     */
    private function findNearestLevel($price, $type)
    {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;
        
        foreach ($this->validateLevels($this->identifySupportResistanceLevels($this->findPivotPoints())) as $level) {
            if ($level['type'] !== $type) continue;
            
            $distance = abs($level['avg_price'] - $price);
            if ($distance < $minDistance && $distance > 0) {
                $minDistance = $distance;
                $nearest = $level;
            }
        }
        
        return $nearest;
    }
    
    /**
     * Identify recent breakouts
     */
    private function identifyBreakouts($levels)
    {
        $breakouts = [];
        $lookbackBars = 10;
        
        foreach ($levels as $level) {
            $breakout = $this->checkForBreakout($level, $lookbackBars);
            if ($breakout) {
                $breakouts[] = $breakout;
            }
        }
        
        return $breakouts;
    }
    
    /**
     * Check if a level has been broken recently
     */
    private function checkForBreakout($level, $lookbackBars)
    {
        $levelPrice = $level['avg_price'];
        $startIndex = max(0, $this->currentIndex - $lookbackBars);
        
        for ($i = $startIndex; $i <= $this->currentIndex; $i++) {
            $candle = $this->data[$i];
            
            if ($level['type'] === 'resistance') {
                // Check for upward breakout
                if ($candle['close'] > $levelPrice && $candle['high'] > $levelPrice) {
                    return [
                        'type' => 'bullish_breakout',
                        'level' => $level,
                        'breakout_index' => $i,
                        'breakout_price' => $candle['close'],
                        'volume' => $candle['volume'],
                        'strength' => $this->calculateBreakoutStrength($level, $i)
                    ];
                }
            } else {
                // Check for downward breakout
                if ($candle['close'] < $levelPrice && $candle['low'] < $levelPrice) {
                    return [
                        'type' => 'bearish_breakout',
                        'level' => $level,
                        'breakout_index' => $i,
                        'breakout_price' => $candle['close'],
                        'volume' => $candle['volume'],
                        'strength' => $this->calculateBreakoutStrength($level, $i)
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Calculate breakout strength
     */
    private function calculateBreakoutStrength($level, $breakoutIndex)
    {
        $strength = 0;
        
        // Volume factor
        $avgVolume = $this->getAverageVolume($breakoutIndex, 10);
        $volumeRatio = $this->data[$breakoutIndex]['volume'] / $avgVolume;
        $strength += min($volumeRatio, 3) * 20;
        
        // Price penetration depth
        $levelPrice = $level['avg_price'];
        $breakoutPrice = $this->data[$breakoutIndex]['close'];
        $penetration = abs($breakoutPrice - $levelPrice) / $levelPrice;
        $strength += min($penetration * 1000, 30);
        
        // Follow-through (next few candles)
        $followThrough = $this->checkFollowThrough($breakoutIndex, $level['type'] === 'resistance');
        $strength += $followThrough * 25;
        
        return min(100, round($strength, 1));
    }
    
    /**
     * Check follow-through after breakout
     */
    private function checkFollowThrough($breakoutIndex, $isBullish)
    {
        $followThroughBars = 3;
        $strength = 0;
        
        for ($i = $breakoutIndex + 1; $i <= min($this->currentIndex, $breakoutIndex + $followThroughBars); $i++) {
            if ($i >= count($this->data)) break;
            
            $candle = $this->data[$i];
            
            if ($isBullish && $candle['close'] > $candle['open']) {
                $strength += 0.33;
            } elseif (!$isBullish && $candle['close'] < $candle['open']) {
                $strength += 0.33;
            }
        }
        
        return $strength;
    }
    
    /**
     * Generate trading signals
     */
    private function generateTradingSignals($levels, $currentPrice)
    {
        $signals = [];
        
        foreach ($levels as $level) {
            $signal = $this->generateSignalForLevel($level, $currentPrice);
            if ($signal) {
                $signals[] = $signal;
            }
        }
        
        // Sort by confidence
        usort($signals, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $signals;
    }
    
    /**
     * Generate signal for specific level
     */
    private function generateSignalForLevel($level, $currentPrice)
    {
        $signal = null;
        $levelPrice = $level['avg_price'];
        $distance = abs($levelPrice - $currentPrice) / $currentPrice;
        
        // Only generate signals for nearby levels
        if ($distance > 0.05) return null;
        
        if ($level['type'] === 'support' && $currentPrice > $levelPrice) {
            $signal = [
                'type' => 'buy',
                'reason' => 'Price above strong support level',
                'level' => $level,
                'entry_price' => $currentPrice,
                'stop_loss' => $levelPrice * 0.998,
                'take_profit_1' => $currentPrice * 1.02,
                'take_profit_2' => $currentPrice * 1.04,
                'confidence' => $this->calculateSignalConfidence($level, 'buy'),
                'risk_reward' => $level['risk_reward']
            ];
        } elseif ($level['type'] === 'resistance' && $currentPrice < $levelPrice) {
            $signal = [
                'type' => 'sell',
                'reason' => 'Price below strong resistance level',
                'level' => $level,
                'entry_price' => $currentPrice,
                'stop_loss' => $levelPrice * 1.002,
                'take_profit_1' => $currentPrice * 0.98,
                'take_profit_2' => $currentPrice * 0.96,
                'confidence' => $this->calculateSignalConfidence($level, 'sell'),
                'risk_reward' => $level['risk_reward']
            ];
        }
        
        return $signal;
    }
    
    /**
     * Calculate signal confidence
     */
    private function calculateSignalConfidence($level, $signalType)
    {
        $confidence = $level['confidence'] * 0.6; // Base from level confidence
        
        // Technical indicators confluence
        $current = $this->data[$this->currentIndex];
        
        if ($signalType === 'buy') {
            // Bullish indicators
            if ($current['rsi6'] < 30) $confidence += 10;
            if ($current['stoch_rsi'] < 0.2) $confidence += 8;
            if ($current['close'] > $current['ma7']) $confidence += 5;
            if ($current['histogram'] > 0) $confidence += 5;
            if ($current['di_plus'] > $current['di_minus']) $confidence += 5;
        } else {
            // Bearish indicators
            if ($current['rsi6'] > 70) $confidence += 10;
            if ($current['stoch_rsi'] > 0.8) $confidence += 8;
            if ($current['close'] < $current['ma7']) $confidence += 5;
            if ($current['histogram'] < 0) $confidence += 5;
            if ($current['di_minus'] > $current['di_plus']) $confidence += 5;
        }
        
        // Volume confirmation
        $avgVolume = $this->getAverageVolume($this->currentIndex, 10);
        if ($current['volume'] > $avgVolume * 1.2) {
            $confidence += 8;
        }
        
        return min(100, round($confidence, 1));
    }
    
    /**
     * Calculate confidence scores for the analysis
     */
    private function calculateConfidenceScores($levels, $signals)
    {
        $longConfidence = 0;
        $shortConfidence = 0;
        $longCount = 0;
        $shortCount = 0;
        
        foreach ($signals as $signal) {
            if ($signal['type'] === 'buy') {
                $longConfidence += $signal['confidence'];
                $longCount++;
            } else {
                $shortConfidence += $signal['confidence'];
                $shortCount++;
            }
        }
        
        return [
            'long_confidence' => $longCount > 0 ? round($longConfidence / $longCount, 1) : 0,
            'short_confidence' => $shortCount > 0 ? round($shortConfidence / $shortCount, 1) : 0,
            'overall_bias' => $this->determineOverallBias($levels),
            'signal_strength' => $this->calculateOverallSignalStrength($signals),
            'risk_assessment' => $this->assessOverallRisk($levels, $signals)
        ];
    }
    
    /**
     * Determine overall market bias
     */
    private function determineOverallBias($levels)
    {
        $supportLevels = array_filter($levels, function($level) {
            return $level['type'] === 'support' && $level['proximity'] !== 'distant';
        });
        
        $resistanceLevels = array_filter($levels, function($level) {
            return $level['type'] === 'resistance' && $level['proximity'] !== 'distant';
        });
        
        $supportStrength = array_sum(array_column($supportLevels, 'confidence'));
        $resistanceStrength = array_sum(array_column($resistanceLevels, 'confidence'));
        
        if ($supportStrength > $resistanceStrength * 1.2) {
            return 'bullish';
        } elseif ($resistanceStrength > $supportStrength * 1.2) {
            return 'bearish';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * Calculate overall signal strength
     */
    private function calculateOverallSignalStrength($signals)
    {
        if (empty($signals)) return 0;
        
        $totalConfidence = array_sum(array_column($signals, 'confidence'));
        return round($totalConfidence / count($signals), 1);
    }
    
    /**
     * Assess overall risk
     */
    private function assessOverallRisk($levels, $signals)
    {
        $riskFactors = [];
        
        // Market structure risk
        $majorLevels = array_filter($levels, function($level) {
            return $level['classification'] === 'major' && $level['proximity'] === 'near';
        });
        
        if (count($majorLevels) > 2) {
            $riskFactors[] = 'Multiple major S/R levels nearby - increased volatility risk';
        }
        
        // Signal quality risk
        $highConfidenceSignals = array_filter($signals, function($signal) {
            return $signal['confidence'] > 70;
        });
        
        if (count($highConfidenceSignals) === 0) {
            $riskFactors[] = 'No high-confidence signals - lower probability setups';
        }
        
        // Risk/reward assessment
        foreach ($signals as $signal) {
            if ($signal['risk_reward'] && $signal['risk_reward']['ratio'] < 1.5) {
                $riskFactors[] = 'Poor risk/reward ratio on some signals';
                break;
            }
        }
        
        return [
            'level' => count($riskFactors) > 2 ? 'high' : (count($riskFactors) > 0 ? 'medium' : 'low'),
            'factors' => $riskFactors
        ];
    }
    
    /**
     * Analyze market structure
     */
    private function analyzeMarketStructure($levels, $currentPrice)
    {
        $supports = array_filter($levels, function($level) use ($currentPrice) {
            return $level['type'] === 'support' && $level['avg_price'] < $currentPrice;
        });
        
        $resistances = array_filter($levels, function($level) use ($currentPrice) {
            return $level['type'] === 'resistance' && $level['avg_price'] > $currentPrice;
        });
        
        // Sort by proximity to current price
        usort($supports, function($a, $b) use ($currentPrice) {
            $distA = abs($a['avg_price'] - $currentPrice);
            $distB = abs($b['avg_price'] - $currentPrice);
            return $distA <=> $distB;
        });
        
        usort($resistances, function($a, $b) use ($currentPrice) {
            $distA = abs($a['avg_price'] - $currentPrice);
            $distB = abs($b['avg_price'] - $currentPrice);
            return $distA <=> $distB;
        });
        
        return [
            'nearest_support' => !empty($supports) ? $supports[0] : null,
            'nearest_resistance' => !empty($resistances) ? $resistances[0] : null,
            'support_count' => count($supports),
            'resistance_count' => count($resistances),
            'structure_type' => $this->determineStructureType($supports, $resistances, $currentPrice),
            'trend_direction' => $this->analyzeTrendDirection(),
            'key_levels' => array_merge(
                array_slice($supports, 0, 3),
                array_slice($resistances, 0, 3)
            )
        ];
    }
    
    /**
     * Determine market structure type
     */
    private function determineStructureType($supports, $resistances, $currentPrice)
    {
        $nearSupport = !empty($supports) ? $supports[0] : null;
        $nearResistance = !empty($resistances) ? $resistances[0] : null;
        
        if (!$nearSupport || !$nearResistance) {
            return 'undefined';
        }
        
        $supportDistance = abs($currentPrice - $nearSupport['avg_price']) / $currentPrice;
        $resistanceDistance = abs($nearResistance['avg_price'] - $currentPrice) / $currentPrice;
        
        if ($supportDistance < 0.02 && $resistanceDistance < 0.02) {
            return 'consolidation';
        } elseif ($supportDistance < 0.05 || $resistanceDistance < 0.05) {
            return 'range_bound';
        } else {
            return 'trending';
        }
    }
    
    /**
     * Analyze trend direction using price action
     */
    private function analyzeTrendDirection()
    {
        if ($this->currentIndex < 20) return 'insufficient_data';
        
        $recent = array_slice($this->data, $this->currentIndex - 19, 20);
        $highs = array_column($recent, 'high');
        $lows = array_column($recent, 'low');
        
        $higherHighs = 0;
        $lowerHighs = 0;
        $higherLows = 0;
        $lowerLows = 0;
        
        for ($i = 1; $i < count($recent); $i++) {
            if ($highs[$i] > $highs[$i-1]) $higherHighs++;
            else $lowerHighs++;
            
            if ($lows[$i] > $lows[$i-1]) $higherLows++;
            else $lowerLows++;
        }
        
        if ($higherHighs > $lowerHighs && $higherLows > $lowerLows) {
            return 'uptrend';
        } elseif ($lowerHighs > $higherHighs && $lowerLows > $higherLows) {
            return 'downtrend';
        } else {
            return 'sideways';
        }
    }
    
    /**
     * Get support/resistance indexes
     */
    private function getSRIndexes($levels)
    {
        $indexes = [
            'support_indexes' => [],
            'resistance_indexes' => [],
            'latest_support_index' => null,
            'latest_resistance_index' => null
        ];
        
        foreach ($levels as $level) {
            $touchIndexes = array_column($level['touches'], 'index');
            
            if ($level['type'] === 'support') {
                $indexes['support_indexes'] = array_merge($indexes['support_indexes'], $touchIndexes);
                $indexes['latest_support_index'] = max($indexes['latest_support_index'] ?? 0, max($touchIndexes));
            } else {
                $indexes['resistance_indexes'] = array_merge($indexes['resistance_indexes'], $touchIndexes);
                $indexes['latest_resistance_index'] = max($indexes['latest_resistance_index'] ?? 0, max($touchIndexes));
            }
        }
        
        // Remove duplicates and sort
        $indexes['support_indexes'] = array_unique($indexes['support_indexes']);
        $indexes['resistance_indexes'] = array_unique($indexes['resistance_indexes']);
        sort($indexes['support_indexes']);
        sort($indexes['resistance_indexes']);
        
        return $indexes;
    }
    
    /**
     * Generate comprehensive analysis summary
     */
    private function generateAnalysisSummary($levels, $signals, $confidenceScores)
    {
        $summary = [
            'overview' => '',
            'key_insights' => [],
            'trading_recommendations' => [],
            'risk_warnings' => []
        ];
        
        // Overview
        $majorLevels = array_filter($levels, function($level) {
            return $level['classification'] === 'major';
        });
        
        $summary['overview'] = sprintf(
            "Analysis identified %d total S/R levels (%d major, %d minor). Current market bias: %s. Overall signal strength: %s%%.",
            count($levels),
            count($majorLevels),
            count($levels) - count($majorLevels),
            $confidenceScores['overall_bias'],
            $confidenceScores['signal_strength']
        );
        
        // Key insights
        if ($confidenceScores['overall_bias'] !== 'neutral') {
            $summary['key_insights'][] = sprintf(
                "Market shows %s bias with %s confidence in %s signals",
                $confidenceScores['overall_bias'],
                $confidenceScores['overall_bias'] === 'bullish' ? $confidenceScores['long_confidence'] : $confidenceScores['short_confidence'],
                $confidenceScores['overall_bias'] === 'bullish' ? 'long' : 'short'
            );
        }
        
        $nearbyLevels = array_filter($levels, function($level) {
            return $level['proximity'] === 'immediate' || $level['proximity'] === 'near';
        });
        
        if (!empty($nearbyLevels)) {
            $summary['key_insights'][] = sprintf(
                "%d significant S/R levels are near current price, suggesting potential volatility",
                count($nearbyLevels)
            );
        }
        
        // Trading recommendations
        $highConfidenceSignals = array_filter($signals, function($signal) {
            return $signal['confidence'] > 70;
        });
        
        if (!empty($highConfidenceSignals)) {
            foreach (array_slice($highConfidenceSignals, 0, 2) as $signal) {
                $summary['trading_recommendations'][] = sprintf(
                    "%s signal with %s%% confidence. Entry: %s, SL: %s, TP: %s (R:R = %s)",
                    strtoupper($signal['type']),
                    $signal['confidence'],
                    number_format($signal['entry_price'], 1),
                    number_format($signal['stop_loss'], 1),
                    number_format($signal['take_profit_1'], 1),
                    $signal['risk_reward'] ? $signal['risk_reward']['ratio'] : 'N/A'
                );
            }
        } else {
            $summary['trading_recommendations'][] = "No high-confidence signals currently. Wait for better setups.";
        }
        
        // Risk warnings
        if ($confidenceScores['risk_assessment']['level'] === 'high') {
            $summary['risk_warnings'][] = "HIGH RISK: " . implode(', ', $confidenceScores['risk_assessment']['factors']);
        } elseif ($confidenceScores['risk_assessment']['level'] === 'medium') {
            $summary['risk_warnings'][] = "MEDIUM RISK: " . implode(', ', $confidenceScores['risk_assessment']['factors']);
        }
        
        return $summary;
    }
    
    /**
     * Get average volume for a period
     */
    private function getAverageVolume($index, $period)
    {
        $start = max(0, $index - $period + 1);
        $volumes = [];
        
        for ($i = $start; $i <= $index; $i++) {
            if (isset($this->data[$i]['volume'])) {
                $volumes[] = $this->data[$i]['volume'];
            }
        }
        
        return !empty($volumes) ? array_sum($volumes) / count($volumes) : 1;
    }
}