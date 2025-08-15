<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SmartMoneyConceptsService
{
    private array $config;
    private array $cache;
    private array $structures;
    private array $orderBlocks;
    private array $fvgs;
    private array $pivots;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'swing_length' => 5,
            'max_order_blocks' => 5,
            'max_fvgs' => 5,
            'swing_limit' => 100,
            'ob_mitigation_method' => 'close', // 'close', 'wick', 'avg'
            'fvg_mitigation_method' => 'close',
            'fvg_threshold' => 0.0,
            'build_sweeps' => true,
            'overlap_filter' => true,
            'atr_length' => 200,
            'ob_mode' => 'length', // 'length' or 'full'
            'ob_length_multiplier' => 1.0
        ], $config);

        $this->resetCache();
    }

    private function resetCache(): void
    {
        $this->cache = [
            'atr_values' => [],
            'highs' => [],
            'lows' => [],
            'closes' => [],
            'opens' => [],
            'volumes' => [],
            'timestamps' => []
        ];

        $this->structures = [
            'trend' => 0, // 1 = bullish, -1 = bearish, 0 = neutral
            'last_bos_high' => null,
            'last_bos_low' => null,
            'last_choch_high' => null,
            'last_choch_low' => null,
            'structure_points' => [],
            'sweeps' => []
        ];

        $this->orderBlocks = [
            'bullish' => [],
            'bearish' => []
        ];

        $this->fvgs = [
            'bullish' => [],
            'bearish' => []
        ];

        $this->pivots = [
            'highs' => [],
            'lows' => []
        ];
    }

    public function analyze(array $data, int $currentIndex): array
    {
        if ($currentIndex < $this->config['swing_length'] * 2) {
            return $this->getEmptyResult();
        }

        // Update cache with current candle data
        $this->updateCache($data, $currentIndex);

        // Calculate ATR for the current position
        $atr = $this->calculateATR($currentIndex);

        // Detect pivot points
        $this->detectPivots($currentIndex);

        // Analyze market structure
        $this->analyzeMarketStructure($data, $currentIndex, $atr);

        // Detect order blocks
        $this->detectOrderBlocks($data, $currentIndex, $atr);

        // Detect Fair Value Gaps
        $this->detectFairValueGaps($data, $currentIndex);

        // Update mitigations
        $this->updateMitigations($data, $currentIndex);

        // Apply overlap filters
        if ($this->config['overlap_filter']) {
            $this->filterOverlaps();
        }

        // Limit arrays to max size
        $this->limitArraySizes();

        return $this->generateOutput($data, $currentIndex);
    }

    private function updateCache(array $data, int $currentIndex): void
    {
        $current = $data[$currentIndex];
        
        $this->cache['highs'][] = $current['high'];
        $this->cache['lows'][] = $current['low'];
        $this->cache['closes'][] = $current['close'];
        $this->cache['opens'][] = $current['open'];
        $this->cache['volumes'][] = $current['volume'];
        $this->cache['timestamps'][] = $current['timestamp'];

        // Keep only necessary historical data
        $maxLength = max($this->config['atr_length'], $this->config['swing_limit']) + 10;
        if (count($this->cache['highs']) > $maxLength) {
            $this->cache['highs'] = array_slice($this->cache['highs'], -$maxLength);
            $this->cache['lows'] = array_slice($this->cache['lows'], -$maxLength);
            $this->cache['closes'] = array_slice($this->cache['closes'], -$maxLength);
            $this->cache['opens'] = array_slice($this->cache['opens'], -$maxLength);
            $this->cache['volumes'] = array_slice($this->cache['volumes'], -$maxLength);
            $this->cache['timestamps'] = array_slice($this->cache['timestamps'], -$maxLength);
        }
    }

    private function calculateATR(int $currentIndex): float
    {
        $length = min($this->config['atr_length'], count($this->cache['highs']) - 1);
        if ($length < 2) return 0.001;

        $trueRanges = [];
        $cacheLength = count($this->cache['highs']);
        
        for ($i = 1; $i < $length; $i++) {
            $idx = $cacheLength - 1 - $i;
            if ($idx <= 0) break;
            
            $high = $this->cache['highs'][$idx];
            $low = $this->cache['lows'][$idx];
            $prevClose = $this->cache['closes'][$idx - 1];
            
            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            $trueRanges[] = $tr;
        }

        return empty($trueRanges) ? 0.001 : array_sum($trueRanges) / count($trueRanges);
    }

    private function detectPivots(int $currentIndex): void
    {
        $length = $this->config['swing_length'];
        $cacheLength = count($this->cache['highs']);
        
        if ($cacheLength < $length * 2 + 1) return;

        $centerIdx = $cacheLength - 1 - $length;
        if ($centerIdx < 0) return;

        // Check for pivot high
        $isPivotHigh = true;
        $centerHigh = $this->cache['highs'][$centerIdx];
        
        for ($i = $centerIdx - $length; $i <= $centerIdx + $length; $i++) {
            if ($i != $centerIdx && $i >= 0 && $i < $cacheLength) {
                if ($this->cache['highs'][$i] >= $centerHigh) {
                    $isPivotHigh = false;
                    break;
                }
            }
        }

        if ($isPivotHigh) {
            $this->pivots['highs'][] = [
                'price' => $centerHigh,
                'index' => $currentIndex - $length,
                'timestamp' => $this->cache['timestamps'][$centerIdx] ?? null
            ];
        }

        // Check for pivot low
        $isPivotLow = true;
        $centerLow = $this->cache['lows'][$centerIdx];
        
        for ($i = $centerIdx - $length; $i <= $centerIdx + $length; $i++) {
            if ($i != $centerIdx && $i >= 0 && $i < $cacheLength) {
                if ($this->cache['lows'][$i] <= $centerLow) {
                    $isPivotLow = false;
                    break;
                }
            }
        }

        if ($isPivotLow) {
            $this->pivots['lows'][] = [
                'price' => $centerLow,
                'index' => $currentIndex - $length,
                'timestamp' => $this->cache['timestamps'][$centerIdx] ?? null
            ];
        }

        // Keep only recent pivots
        $maxPivots = 50;
        if (count($this->pivots['highs']) > $maxPivots) {
            $this->pivots['highs'] = array_slice($this->pivots['highs'], -$maxPivots);
        }
        if (count($this->pivots['lows']) > $maxPivots) {
            $this->pivots['lows'] = array_slice($this->pivots['lows'], -$maxPivots);
        }
    }

    private function analyzeMarketStructure(array $data, int $currentIndex, float $atr): void
    {
        $current = $data[$currentIndex];
        $currentClose = $current['close'];
        $currentHigh = $current['high'];
        $currentLow = $current['low'];

        // Find recent significant highs and lows
        $recentHighs = array_slice($this->pivots['highs'], -10);
        $recentLows = array_slice($this->pivots['lows'], -10);

        if (empty($recentHighs) || empty($recentLows)) return;

        $lastSignificantHigh = end($recentHighs);
        $lastSignificantLow = end($recentLows);

        // Determine current trend based on structure
        $previousTrend = $this->structures['trend'];

        // Check for Break of Structure (BOS)
        if ($this->structures['trend'] == 1) { // Bullish trend
            // Look for BOS to the upside (break of recent high)
            if ($currentHigh > $lastSignificantHigh['price']) {
                $this->structures['last_bos_high'] = [
                    'price' => $currentHigh,
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'type' => 'BOS'
                ];
            }
            
            // Look for CHoCH to the downside (break of recent low)
            if ($currentClose < $lastSignificantLow['price']) {
                $this->structures['trend'] = -1;
                $this->structures['last_choch_low'] = [
                    'price' => $currentLow,
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'type' => 'CHoCH'
                ];
                
                // Create bearish order block from previous swing high
                $this->createOrderBlockFromStructure(false, $currentIndex, $data, $atr);
            }
            
        } elseif ($this->structures['trend'] == -1) { // Bearish trend
            // Look for BOS to the downside (break of recent low)
            if ($currentLow < $lastSignificantLow['price']) {
                $this->structures['last_bos_low'] = [
                    'price' => $currentLow,
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'type' => 'BOS'
                ];
            }
            
            // Look for CHoCH to the upside (break of recent high)
            if ($currentClose > $lastSignificantHigh['price']) {
                $this->structures['trend'] = 1;
                $this->structures['last_choch_high'] = [
                    'price' => $currentHigh,
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'type' => 'CHoCH'
                ];
                
                // Create bullish order block from previous swing low
                $this->createOrderBlockFromStructure(true, $currentIndex, $data, $atr);
            }
        } else {
            // Neutral trend - establish initial trend
            if (count($recentHighs) >= 2 && count($recentLows) >= 2) {
                $prevHigh = $recentHighs[count($recentHighs) - 2];
                $prevLow = $recentLows[count($recentLows) - 2];
                
                if ($currentClose > $lastSignificantHigh['price'] && $lastSignificantHigh['price'] > $prevHigh['price']) {
                    $this->structures['trend'] = 1;
                } elseif ($currentClose < $lastSignificantLow['price'] && $lastSignificantLow['price'] < $prevLow['price']) {
                    $this->structures['trend'] = -1;
                }
            }
        }

        // Detect sweeps if enabled
        if ($this->config['build_sweeps']) {
            $this->detectSweeps($data, $currentIndex);
        }
    }

    private function createOrderBlockFromStructure(bool $isBullish, int $currentIndex, array $data, float $atr): void
    {
        // Find the candle that created the order block
        $lookbackRange = min(20, $currentIndex);
        $obIndex = null;
        $obCandle = null;

        if ($isBullish) {
            // Look for the last down candle before the move up
            for ($i = 1; $i <= $lookbackRange; $i++) {
                $idx = $currentIndex - $i;
                if ($idx < 0) break;
                
                $candle = $data[$idx];
                if ($candle['close'] < $candle['open']) { // Down candle
                    $obIndex = $idx;
                    $obCandle = $candle;
                    break;
                }
            }
        } else {
            // Look for the last up candle before the move down
            for ($i = 1; $i <= $lookbackRange; $i++) {
                $idx = $currentIndex - $i;
                if ($idx < 0) break;
                
                $candle = $data[$idx];
                if ($candle['close'] > $candle['open']) { // Up candle
                    $obIndex = $idx;
                    $obCandle = $candle;
                    break;
                }
            }
        }

        if ($obCandle) {
            $this->createOrderBlock($isBullish, $obCandle, $obIndex, $atr);
        }
    }

    private function detectOrderBlocks(array $data, int $currentIndex, float $atr): void
    {
        // Order blocks are created from market structure changes
        // Main creation logic is in createOrderBlockFromStructure
        // This method can be used for additional OB detection logic if needed
    }

    private function createOrderBlock(bool $isBullish, array $candle, int $index, float $atr): void
    {
        $multiplier = $this->config['ob_length_multiplier'];
        
        if ($isBullish) {
            $top = ($this->config['ob_mode'] === 'length') 
                ? min($candle['high'], $candle['low'] + $atr * $multiplier)
                : $candle['high'];
            $bottom = $candle['low'];
            
            $orderBlock = [
                'top' => $top,
                'bottom' => $bottom,
                'avg' => ($top + $bottom) / 2,
                'index' => $index,
                'timestamp' => $candle['timestamp'],
                'volume' => $candle['volume'],
                'is_bullish' => true,
                'is_mitigated' => false,
                'mitigation_index' => null,
                'is_breaker' => false,
                'strength' => $this->calculateOrderBlockStrength($candle)
            ];
            
            array_unshift($this->orderBlocks['bullish'], $orderBlock);
        } else {
            $bottom = ($this->config['ob_mode'] === 'length') 
                ? max($candle['low'], $candle['high'] - $atr * $multiplier)
                : $candle['low'];
            $top = $candle['high'];
            
            $orderBlock = [
                'top' => $top,
                'bottom' => $bottom,
                'avg' => ($top + $bottom) / 2,
                'index' => $index,
                'timestamp' => $candle['timestamp'],
                'volume' => $candle['volume'],
                'is_bullish' => false,
                'is_mitigated' => false,
                'mitigation_index' => null,
                'is_breaker' => false,
                'strength' => $this->calculateOrderBlockStrength($candle)
            ];
            
            array_unshift($this->orderBlocks['bearish'], $orderBlock);
        }
    }

    private function calculateOrderBlockStrength(array $candle): float
    {
        // Simple strength calculation based on volume and candle size
        $bodySize = abs($candle['close'] - $candle['open']);
        $totalRange = $candle['high'] - $candle['low'];
        $bodyRatio = $totalRange > 0 ? $bodySize / $totalRange : 0;
        
        return $bodyRatio * ($candle['volume'] / 1000000); // Normalized volume
    }

    private function detectFairValueGaps(array $data, int $currentIndex): void
    {
        if ($currentIndex < 2) return;

        $current = $data[$currentIndex];
        $prev1 = $data[$currentIndex - 1];
        $prev2 = $data[$currentIndex - 2];

        // Bullish FVG: Gap between prev2 high and current low
        if ($prev1['low'] > $prev2['high']) {
            $gap = $prev1['low'] - $prev2['high'];
            $atr = $this->calculateATR($currentIndex);
            
            if ($gap > $atr * $this->config['fvg_threshold']) {
                $fvg = [
                    'top' => $prev1['low'],
                    'bottom' => $prev2['high'],
                    'index' => $currentIndex - 2,
                    'timestamp' => $prev2['timestamp'],
                    'is_bullish' => true,
                    'is_mitigated' => false,
                    'mitigation_index' => null,
                    'is_breaker' => false
                ];
                
                array_unshift($this->fvgs['bullish'], $fvg);
            }
        }

        // Bearish FVG: Gap between prev2 low and current high
        if ($prev1['high'] < $prev2['low']) {
            $gap = $prev2['low'] - $prev1['high'];
            $atr = $this->calculateATR($currentIndex);
            
            if ($gap > $atr * $this->config['fvg_threshold']) {
                $fvg = [
                    'top' => $prev2['low'],
                    'bottom' => $prev1['high'],
                    'index' => $currentIndex - 2,
                    'timestamp' => $prev2['timestamp'],
                    'is_bullish' => false,
                    'is_mitigated' => false,
                    'mitigation_index' => null,
                    'is_breaker' => false
                ];
                
                array_unshift($this->fvgs['bearish'], $fvg);
            }
        }
    }

    private function updateMitigations(array $data, int $currentIndex): void
    {
        $current = $data[$currentIndex];
        $method = $this->config['ob_mitigation_method'];

        // Update order block mitigations
        foreach ($this->orderBlocks['bullish'] as &$ob) {
            if (!$ob['is_mitigated']) {
                $price = $this->getMitigationPrice($current, $method);
                if ($price < $ob['bottom'] || ($method === 'avg' && $price < $ob['avg'])) {
                    $ob['is_mitigated'] = true;
                    $ob['mitigation_index'] = $currentIndex;
                }
            } elseif ($ob['is_mitigated'] && !$ob['is_breaker']) {
                $price = $this->getMitigationPrice($current, $method, true); // Use high for breaker
                if ($price > $ob['top'] || ($method === 'avg' && $price > $ob['avg'])) {
                    $ob['is_breaker'] = true;
                }
            }
        }

        foreach ($this->orderBlocks['bearish'] as &$ob) {
            if (!$ob['is_mitigated']) {
                $price = $this->getMitigationPrice($current, $method, true);
                if ($price > $ob['top'] || ($method === 'avg' && $price > $ob['avg'])) {
                    $ob['is_mitigated'] = true;
                    $ob['mitigation_index'] = $currentIndex;
                }
            } elseif ($ob['is_mitigated'] && !$ob['is_breaker']) {
                $price = $this->getMitigationPrice($current, $method);
                if ($price < $ob['bottom'] || ($method === 'avg' && $price < $ob['avg'])) {
                    $ob['is_breaker'] = true;
                }
            }
        }

        // Update FVG mitigations
        $fvgMethod = $this->config['fvg_mitigation_method'];

        foreach ($this->fvgs['bullish'] as &$fvg) {
            if (!$fvg['is_mitigated']) {
                $price = $this->getMitigationPrice($current, $fvgMethod);
                if ($price < $fvg['bottom'] || ($fvgMethod === 'avg' && $price < ($fvg['top'] + $fvg['bottom']) / 2)) {
                    $fvg['is_mitigated'] = true;
                    $fvg['mitigation_index'] = $currentIndex;
                }
            }
        }

        foreach ($this->fvgs['bearish'] as &$fvg) {
            if (!$fvg['is_mitigated']) {
                $price = $this->getMitigationPrice($current, $fvgMethod, true);
                if ($price > $fvg['top'] || ($fvgMethod === 'avg' && $price > ($fvg['top'] + $fvg['bottom']) / 2)) {
                    $fvg['is_mitigated'] = true;
                    $fvg['mitigation_index'] = $currentIndex;
                }
            }
        }
    }

    private function getMitigationPrice(array $candle, string $method, bool $useHigh = false): float
    {
        switch ($method) {
            case 'close':
                return max($candle['close'], $candle['open']);
            case 'wick':
                return $useHigh ? $candle['high'] : $candle['low'];
            case 'avg':
                return ($candle['high'] + $candle['low']) / 2;
            default:
                return $useHigh ? $candle['high'] : $candle['low'];
        }
    }

    private function detectSweeps(array $data, int $currentIndex): void
    {
        if (empty($this->pivots['highs']) || empty($this->pivots['lows'])) return;

        $current = $data[$currentIndex];
        $recentHighs = array_slice($this->pivots['highs'], -5);
        $recentLows = array_slice($this->pivots['lows'], -5);

        // Check for liquidity sweeps on highs
        foreach ($recentHighs as $pivot) {
            if ($current['high'] > $pivot['price'] && $current['close'] < $pivot['price']) {
                $this->structures['sweeps'][] = [
                    'type' => 'high_sweep',
                    'level' => $pivot['price'],
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'sweep_high' => $current['high']
                ];
            }
        }

        // Check for liquidity sweeps on lows
        foreach ($recentLows as $pivot) {
            if ($current['low'] < $pivot['price'] && $current['close'] > $pivot['price']) {
                $this->structures['sweeps'][] = [
                    'type' => 'low_sweep',
                    'level' => $pivot['price'],
                    'index' => $currentIndex,
                    'timestamp' => $current['timestamp'],
                    'sweep_low' => $current['low']
                ];
            }
        }

        // Keep only recent sweeps
        if (count($this->structures['sweeps']) > 20) {
            $this->structures['sweeps'] = array_slice($this->structures['sweeps'], -20);
        }
    }

    private function filterOverlaps(): void
    {
        // Filter overlapping order blocks
        $this->orderBlocks['bullish'] = $this->removeOverlappingLevels($this->orderBlocks['bullish']);
        $this->orderBlocks['bearish'] = $this->removeOverlappingLevels($this->orderBlocks['bearish']);

        // Filter overlapping FVGs
        $this->fvgs['bullish'] = $this->removeOverlappingLevels($this->fvgs['bullish']);
        $this->fvgs['bearish'] = $this->removeOverlappingLevels($this->fvgs['bearish']);
    }

    private function removeOverlappingLevels(array $levels): array
    {
        if (count($levels) <= 1) return $levels;

        $filtered = [$levels[0]]; // Keep the most recent (first) level

        for ($i = 1; $i < count($levels); $i++) {
            $current = $levels[$i];
            $hasOverlap = false;

            foreach ($filtered as $existing) {
                if ($this->levelsOverlap($current, $existing)) {
                    $hasOverlap = true;
                    break;
                }
            }

            if (!$hasOverlap) {
                $filtered[] = $current;
            }
        }

        return $filtered;
    }

    private function levelsOverlap(array $level1, array $level2): bool
    {
        return !($level1['top'] < $level2['bottom'] || $level1['bottom'] > $level2['top']);
    }

    private function limitArraySizes(): void
    {
        $maxOB = $this->config['max_order_blocks'];
        $maxFVG = $this->config['max_fvgs'];

        if (count($this->orderBlocks['bullish']) > $maxOB) {
            $this->orderBlocks['bullish'] = array_slice($this->orderBlocks['bullish'], 0, $maxOB);
        }
        if (count($this->orderBlocks['bearish']) > $maxOB) {
            $this->orderBlocks['bearish'] = array_slice($this->orderBlocks['bearish'], 0, $maxOB);
        }
        if (count($this->fvgs['bullish']) > $maxFVG) {
            $this->fvgs['bullish'] = array_slice($this->fvgs['bullish'], 0, $maxFVG);
        }
        if (count($this->fvgs['bearish']) > $maxFVG) {
            $this->fvgs['bearish'] = array_slice($this->fvgs['bearish'], 0, $maxFVG);
        }
    }

    private function generateOutput(array $data, int $currentIndex): array
    {
        return [
            'market_structure' => [
                'trend' => $this->structures['trend'],
                'trend_name' => $this->getTrendName($this->structures['trend']),
                'last_bos_high' => $this->structures['last_bos_high'],
                'last_bos_low' => $this->structures['last_bos_low'],
                'last_choch_high' => $this->structures['last_choch_high'],
                'last_choch_low' => $this->structures['last_choch_low'],
                'sweeps' => $this->structures['sweeps']
            ],
            'order_blocks' => [
                'bullish' => $this->orderBlocks['bullish'],
                'bearish' => $this->orderBlocks['bearish']
            ],
            'fair_value_gaps' => [
                'bullish' => $this->fvgs['bullish'],
                'bearish' => $this->fvgs['bearish']
            ],
            'pivot_points' => [
                'highs' => array_slice($this->pivots['highs'], -10),
                'lows' => array_slice($this->pivots['lows'], -10)
            ],
            'current_analysis' => [
                'index' => $currentIndex,
                'timestamp' => $data[$currentIndex]['timestamp'],
                'price' => $data[$currentIndex]['close'],
                'atr' => $this->calculateATR($currentIndex)
            ]
        ];
    }

    private function getTrendName(int $trend): string
    {
        switch ($trend) {
            case 1: return 'Bullish';
            case -1: return 'Bearish';
            default: return 'Neutral';
        }
    }

    private function getEmptyResult(): array
    {
        return [
            'market_structure' => [
                'trend' => 0,
                'trend_name' => 'Neutral',
                'last_bos_high' => null,
                'last_bos_low' => null,
                'last_choch_high' => null,
                'last_choch_low' => null,
                'sweeps' => []
            ],
            'order_blocks' => [
                'bullish' => [],
                'bearish' => []
            ],
            'fair_value_gaps' => [
                'bullish' => [],
                'bearish' => []
            ],
            'pivot_points' => [
                'highs' => [],
                'lows' => []
            ],
            'current_analysis' => [
                'index' => 0,
                'timestamp' => null,
                'price' => 0,
                'atr' => 0
            ]
        ];
    }

    // Public methods for configuration and utilities
    
    public function updateConfig(array $newConfig): void
    {
        $this->config = array_merge($this->config, $newConfig);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function reset(): void
    {
        $this->resetCache();
    }

    public function getCurrentTrend(): int
    {
        return $this->structures['trend'];
    }

    public function getActiveOrderBlocks(): array
    {
        return [
            'bullish' => array_filter($this->orderBlocks['bullish'], function($ob) {
                return !$ob['is_mitigated'];
            }),
            'bearish' => array_filter($this->orderBlocks['bearish'], function($ob) {
                return !$ob['is_mitigated'];
            })
        ];
    }

    public function getActiveFVGs(): array
    {
        return [
            'bullish' => array_filter($this->fvgs['bullish'], function($fvg) {
                return !$fvg['is_mitigated'];
            }),
            'bearish' => array_filter($this->fvgs['bearish'], function($fvg) {
                return !$fvg['is_mitigated'];
            })
        ];
    }

    public function isInOrderBlock(float $price): ?array
    {
        // Check bullish order blocks
        foreach ($this->orderBlocks['bullish'] as $ob) {
            if (!$ob['is_mitigated'] && $price >= $ob['bottom'] && $price <= $ob['top']) {
                return array_merge($ob, ['type' => 'bullish_order_block']);
            }
        }

        // Check bearish order blocks
        foreach ($this->orderBlocks['bearish'] as $ob) {
            if (!$ob['is_mitigated'] && $price >= $ob['bottom'] && $price <= $ob['top']) {
                return array_merge($ob, ['type' => 'bearish_order_block']);
            }
        }

        return null;
    }

    public function isInFVG(float $price): ?array
    {
        // Check bullish FVGs
        foreach ($this->fvgs['bullish'] as $fvg) {
            if (!$fvg['is_mitigated'] && $price >= $fvg['bottom'] && $price <= $fvg['top']) {
                return array_merge($fvg, ['type' => 'bullish_fvg']);
            }
        }

        // Check bearish FVGs
        foreach ($this->fvgs['bearish'] as $fvg) {
            if (!$fvg['is_mitigated'] && $price >= $fvg['bottom'] && $price <= $fvg['top']) {
                return array_merge($fvg, ['type' => 'bearish_fvg']);
            }
        }

        return null;
    }

    public function getNearestLevels(float $currentPrice, int $count = 3): array
    {
        $allLevels = [];

        // Add order blocks
        foreach ($this->orderBlocks['bullish'] as $ob) {
            if (!$ob['is_mitigated']) {
                $allLevels[] = [
                    'type' => 'bullish_order_block',
                    'price' => $ob['avg'],
                    'top' => $ob['top'],
                    'bottom' => $ob['bottom'],
                    'distance' => abs($currentPrice - $ob['avg']),
                    'data' => $ob
                ];
            }
        }

        foreach ($this->orderBlocks['bearish'] as $ob) {
            if (!$ob['is_mitigated']) {
                $allLevels[] = [
                    'type' => 'bearish_order_block',
                    'price' => $ob['avg'],
                    'top' => $ob['top'],
                    'bottom' => $ob['bottom'],
                    'distance' => abs($currentPrice - $ob['avg']),
                    'data' => $ob
                ];
            }
        }

        // Add FVGs
        foreach ($this->fvgs['bullish'] as $fvg) {
            if (!$fvg['is_mitigated']) {
                $avgPrice = ($fvg['top'] + $fvg['bottom']) / 2;
                $allLevels[] = [
                    'type' => 'bullish_fvg',
                    'price' => $avgPrice,
                    'top' => $fvg['top'],
                    'bottom' => $fvg['bottom'],
                    'distance' => abs($currentPrice - $avgPrice),
                    'data' => $fvg
                ];
            }
        }

        foreach ($this->fvgs['bearish'] as $fvg) {
            if (!$fvg['is_mitigated']) {
                $avgPrice = ($fvg['top'] + $fvg['bottom']) / 2;
                $allLevels[] = [
                    'type' => 'bearish_fvg',
                    'price' => $avgPrice,
                    'top' => $fvg['top'],
                    'bottom' => $fvg['bottom'],
                    'distance' => abs($currentPrice - $avgPrice),
                    'data' => $fvg
                ];
            }
        }

        // Sort by distance and return closest levels
        usort($allLevels, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return array_slice($allLevels, 0, $count);
    }

    public function getSignals(array $data, int $currentIndex): array
    {
        $signals = [];
        $current = $data[$currentIndex];
        $currentPrice = $current['close'];

        // Signal based on order block interaction
        $obLevel = $this->isInOrderBlock($currentPrice);
        if ($obLevel) {
            $signals[] = [
                'type' => 'order_block_reaction',
                'direction' => $obLevel['is_bullish'] ? 'bullish' : 'bearish',
                'strength' => $obLevel['strength'] ?? 1.0,
                'level' => $obLevel,
                'description' => $obLevel['is_bullish'] 
                    ? 'Price reacting at bullish order block - potential bounce'
                    : 'Price reacting at bearish order block - potential rejection'
            ];
        }

        // Signal based on FVG interaction
        $fvgLevel = $this->isInFVG($currentPrice);
        if ($fvgLevel) {
            $signals[] = [
                'type' => 'fvg_fill',
                'direction' => $fvgLevel['is_bullish'] ? 'bullish' : 'bearish',
                'strength' => 0.7,
                'level' => $fvgLevel,
                'description' => $fvgLevel['is_bullish']
                    ? 'Price filling bullish FVG - potential support'
                    : 'Price filling bearish FVG - potential resistance'
            ];
        }

        // Signal based on market structure
        if (isset($this->structures['last_choch_high']) || isset($this->structures['last_choch_low'])) {
            $recentChoch = $this->structures['last_choch_high'] ?? $this->structures['last_choch_low'];
            if ($recentChoch && ($currentIndex - $recentChoch['index']) <= 5) {
                $signals[] = [
                    'type' => 'structure_change',
                    'direction' => $this->structures['trend'] == 1 ? 'bullish' : 'bearish',
                    'strength' => 0.8,
                    'level' => $recentChoch,
                    'description' => 'Recent change of character detected - trend shift'
                ];
            }
        }

        // Signal based on sweeps
        $recentSweeps = array_filter($this->structures['sweeps'], function($sweep) use ($currentIndex) {
            return ($currentIndex - $sweep['index']) <= 3;
        });

        if (!empty($recentSweeps)) {
            $lastSweep = end($recentSweeps);
            $signals[] = [
                'type' => 'liquidity_sweep',
                'direction' => $lastSweep['type'] === 'high_sweep' ? 'bearish' : 'bullish',
                'strength' => 0.6,
                'level' => $lastSweep,
                'description' => $lastSweep['type'] === 'high_sweep'
                    ? 'Recent high sweep - potential bearish reversal'
                    : 'Recent low sweep - potential bullish reversal'
            ];
        }

        return $signals;
    }

    public function getTradingLevels(float $currentPrice): array
    {
        $levels = [];

        // Get support levels (bullish OBs and FVGs below current price)
        foreach ($this->orderBlocks['bullish'] as $ob) {
            if (!$ob['is_mitigated'] && $ob['top'] < $currentPrice) {
                $levels['support'][] = [
                    'type' => 'order_block',
                    'price' => $ob['avg'],
                    'top' => $ob['top'],
                    'bottom' => $ob['bottom'],
                    'strength' => $ob['strength'] ?? 1.0,
                    'timestamp' => $ob['timestamp']
                ];
            }
        }

        foreach ($this->fvgs['bullish'] as $fvg) {
            if (!$fvg['is_mitigated'] && $fvg['top'] < $currentPrice) {
                $levels['support'][] = [
                    'type' => 'fvg',
                    'price' => ($fvg['top'] + $fvg['bottom']) / 2,
                    'top' => $fvg['top'],
                    'bottom' => $fvg['bottom'],
                    'strength' => 0.7,
                    'timestamp' => $fvg['timestamp']
                ];
            }
        }

        // Get resistance levels (bearish OBs and FVGs above current price)
        foreach ($this->orderBlocks['bearish'] as $ob) {
            if (!$ob['is_mitigated'] && $ob['bottom'] > $currentPrice) {
                $levels['resistance'][] = [
                    'type' => 'order_block',
                    'price' => $ob['avg'],
                    'top' => $ob['top'],
                    'bottom' => $ob['bottom'],
                    'strength' => $ob['strength'] ?? 1.0,
                    'timestamp' => $ob['timestamp']
                ];
            }
        }

        foreach ($this->fvgs['bearish'] as $fvg) {
            if (!$fvg['is_mitigated'] && $fvg['bottom'] > $currentPrice) {
                $levels['resistance'][] = [
                    'type' => 'fvg',
                    'price' => ($fvg['top'] + $fvg['bottom']) / 2,
                    'top' => $fvg['top'],
                    'bottom' => $fvg['bottom'],
                    'strength' => 0.7,
                    'timestamp' => $fvg['timestamp']
                ];
            }
        }

        // Sort levels by distance from current price
        if (isset($levels['support'])) {
            usort($levels['support'], function($a, $b) use ($currentPrice) {
                return abs($currentPrice - $b['price']) <=> abs($currentPrice - $a['price']);
            });
            $levels['support'] = array_slice($levels['support'], 0, 5);
        }

        if (isset($levels['resistance'])) {
            usort($levels['resistance'], function($a, $b) use ($currentPrice) {
                return abs($currentPrice - $a['price']) <=> abs($currentPrice - $b['price']);
            });
            $levels['resistance'] = array_slice($levels['resistance'], 0, 5);
        }

        return $levels;
    }
}