<?php

namespace App\Services;

class OrderBlockService
{
    // Configuration constants
    const MAX_ORDER_BLOCKS = 30;
    const MAX_ATR_MULT = 3.5;
    const OVERLAP_THRESHOLD_PERCENTAGE = 0;
    
    // Service properties
    private int $swingLength;
    private string $obEndMethod;
    private int $bullishOrderBlocks;
    private int $bearishOrderBlocks;
    private bool $combineOBs;
    
    // State arrays
    private array $bullishOrderBlocksList = [];
    private array $bearishOrderBlocksList = [];
    private array $swingHistory = [];
    private float $atr = 0.0;
    
    // Swing state tracking
    private int $swingType = 0; // 0 = high, 1 = low
    private ?array $topSwing = null;
    private ?array $bottomSwing = null;
    
    public function __construct(
        int $swingLength = 10,
        string $obEndMethod = 'Wick',
        string $zoneCount = 'Low',
        bool $combineOBs = true
    ) {
        $this->swingLength = max(3, $swingLength);
        $this->obEndMethod = in_array($obEndMethod, ['Wick', 'Close']) ? $obEndMethod : 'Wick';
        $this->combineOBs = $combineOBs;
        
        // Set zone counts based on configuration
        $zoneCounts = [
            'One' => 1,
            'Low' => 3,
            'Medium' => 5,
            'High' => 10
        ];
        
        $this->bullishOrderBlocks = $zoneCounts[$zoneCount] ?? 3;
        $this->bearishOrderBlocks = $zoneCounts[$zoneCount] ?? 3;
    }
    
    /**
     * Main method to calculate order blocks up to a specific index
     * Exactly matches Pine Script logic flow
     */
    public function calculateOrderBlocks(array $data, int $index): array
    {
        // Reset state for fresh calculation
        $this->resetState();
        
        // Calculate ATR for the current period
        $this->calculateATR($data, $index);
        
        // Process each bar up to the current index (matching Pine Script bar processing)
        for ($i = $this->swingLength; $i <= $index; $i++) {
            $this->processBar($data, $i);
        }
        
        return [
            'bullish' => array_slice($this->bullishOrderBlocksList, 0, $this->bullishOrderBlocks),
            'bearish' => array_slice($this->bearishOrderBlocksList, 0, $this->bearishOrderBlocks),
            'atr' => $this->atr
        ];
    }
    
    /**
     * Process a single bar - matches Pine Script findOrderBlocks() exactly
     */
    private function processBar(array $data, int $index): void
    {
        // Find swing points using exact Pine Script logic
        [$top, $btm] = $this->findOBSwings($data, $index);
        
        // Update top and bottom swing references
        if ($top !== null) {
            $this->topSwing = $top;
        }
        if ($btm !== null) {
            $this->bottomSwing = $btm;
        }
        
        // Process bullish order blocks - exact Pine Script logic
        $this->processBullishOrderBlocks($data, $index);
        
        // Process bearish order blocks - exact Pine Script logic  
        $this->processBearishOrderBlocks($data, $index);
    }
    
    /**
     * Exact translation of Pine Script findOBSwings function
     */
    private function findOBSwings(array $data, int $index): array
    {
        $len = $this->swingLength;
        
        // Calculate ta.highest and ta.lowest equivalents
        $upper = $this->getHighest($data, $index, $len);
        $lower = $this->getLowest($data, $index, $len);
        
        $lookbackIndex = $index - $len;
        if ($lookbackIndex < 0 || !isset($data[$lookbackIndex])) {
            return [null, null];
        }
        
        // Exact Pine Script swing type logic
        $prevSwingType = $this->swingType;
        
        if ($data[$lookbackIndex]['high'] > $upper) {
            $this->swingType = 0; // High swing
        } elseif ($data[$lookbackIndex]['low'] < $lower) {
            $this->swingType = 1; // Low swing  
        }
        // Otherwise swingType remains the same (no else clause in Pine Script)
        
        $top = null;
        $btm = null;
        
        // Create new swing high when transitioning to high swing
        if ($this->swingType == 0 && $prevSwingType != 0) {
            $top = [
                'x' => $lookbackIndex,
                'y' => $data[$lookbackIndex]['high'],
                'swingVolume' => $data[$lookbackIndex]['volume'],
                'crossed' => false
            ];
        }
        
        // Create new swing low when transitioning to low swing
        if ($this->swingType == 1 && $prevSwingType != 1) {
            $btm = [
                'x' => $lookbackIndex,
                'y' => $data[$lookbackIndex]['low'],  
                'swingVolume' => $data[$lookbackIndex]['volume'],
                'crossed' => false
            ];
        }
        
        return [$top, $btm];
    }
    
    /**
     * Exact translation of Pine Script bullish order block logic
     */
    private function processBullishOrderBlocks(array $data, int $index): void
    {
        // Process existing bullish order blocks
        for ($i = count($this->bullishOrderBlocksList) - 1; $i >= 0; $i--) {
            $currentOB = &$this->bullishOrderBlocksList[$i];
            
            if (!$currentOB['breaker']) {
                // Check for breaker condition - exact Pine Script logic
                $testValue = $this->obEndMethod === 'Wick' 
                    ? $data[$index]['low'] 
                    : min($data[$index]['open'], $data[$index]['close']);
                    
                if ($testValue < $currentOB['bottom']) {
                    $currentOB['breaker'] = true;
                    $currentOB['breakTime'] = $data[$index]['timestamp'];
                    $currentOB['bbVolume'] = $data[$index]['volume'];
                }
            } else {
                // Remove fully invalidated order blocks
                if ($data[$index]['high'] > $currentOB['top']) {
                    array_splice($this->bullishOrderBlocksList, $i, 1);
                }
            }
        }
        
        // Create new bullish order block - exact Pine Script condition
        if ($this->topSwing !== null && 
            $data[$index]['close'] > $this->topSwing['y'] && 
            !$this->topSwing['crossed']) {
            
            $this->topSwing['crossed'] = true;
            
            // Exact Pine Script order block creation logic
            $useBody = false; // Pine Script has this as false
            $max = $useBody ? max($data[$index - 1]['close'], $data[$index - 1]['open']) : $data[$index - 1]['high'];
            $min = $useBody ? min($data[$index - 1]['close'], $data[$index - 1]['open']) : $data[$index - 1]['low'];
            
            $boxBtm = $max;
            $boxTop = $min;  
            $boxLoc = $data[$index - 1]['timestamp'];
            
            // Find the actual order block boundaries - exact Pine Script loop
            for ($i = 1; $i < ($index - $this->topSwing['x']) - 1; $i++) {
                $checkIndex = $index - 1 - $i;
                if ($checkIndex < 0 || !isset($data[$checkIndex])) {
                    break;
                }
                
                $currentMin = $useBody ? min($data[$checkIndex]['close'], $data[$checkIndex]['open']) : $data[$checkIndex]['low'];
                $currentMax = $useBody ? max($data[$checkIndex]['close'], $data[$checkIndex]['open']) : $data[$checkIndex]['high'];
                
                if ($currentMin < $boxBtm) {
                    $boxBtm = $currentMin;
                    $boxTop = $currentMax;
                    $boxLoc = $data[$checkIndex]['timestamp'];
                }
            }
            
            // Volume calculations - exact Pine Script logic
            $obVolume = 0;
            $obLowVolume = 0;
            $obHighVolume = 0;
            
            // volume + volume[1] + volume[2] in Pine Script
            for ($i = 0; $i <= 2; $i++) {
                $volIndex = $index - $i;
                if ($volIndex >= 0 && isset($data[$volIndex])) {
                    $obVolume += $data[$volIndex]['volume'];
                    if ($i === 2) {
                        $obLowVolume = $data[$volIndex]['volume']; // volume[2]
                    } else {
                        $obHighVolume += $data[$volIndex]['volume']; // volume + volume[1]
                    }
                }
            }
            
            // ATR size check - exact Pine Script logic
            $obSize = abs($boxTop - $boxBtm);
            if ($obSize <= $this->atr * self::MAX_ATR_MULT) {
                $newOrderBlock = [
                    'top' => $boxTop,
                    'bottom' => $boxBtm,
                    'obVolume' => $obVolume,
                    'obType' => 'Bull',
                    'startTime' => $boxLoc,
                    'bbVolume' => 0,
                    'obLowVolume' => $obLowVolume,
                    'obHighVolume' => $obHighVolume,
                    'breaker' => false,
                    'breakTime' => null,
                    'timeframeStr' => '',
                    'disabled' => false,
                    'combinedTimeframesStr' => null,
                    'combined' => false
                ];
                
                // Add to beginning of array (unshift in Pine Script)
                array_unshift($this->bullishOrderBlocksList, $newOrderBlock);
                
                // Maintain max order blocks limit
                if (count($this->bullishOrderBlocksList) > self::MAX_ORDER_BLOCKS) {
                    array_pop($this->bullishOrderBlocksList);
                }
            }
        }
    }
    
    /**
     * Exact translation of Pine Script bearish order block logic
     */
    private function processBearishOrderBlocks(array $data, int $index): void
    {
        // Process existing bearish order blocks
        for ($i = count($this->bearishOrderBlocksList) - 1; $i >= 0; $i--) {
            $currentOB = &$this->bearishOrderBlocksList[$i];
            
            if (!$currentOB['breaker']) {
                // Check for breaker condition - exact Pine Script logic
                $testValue = $this->obEndMethod === 'Wick' 
                    ? $data[$index]['high'] 
                    : max($data[$index]['open'], $data[$index]['close']);
                    
                if ($testValue > $currentOB['top']) {
                    $currentOB['breaker'] = true;
                    $currentOB['breakTime'] = $data[$index]['timestamp'];
                    $currentOB['bbVolume'] = $data[$index]['volume'];
                }
            } else {
                // Remove fully invalidated order blocks
                if ($data[$index]['low'] < $currentOB['bottom']) {
                    array_splice($this->bearishOrderBlocksList, $i, 1);
                }
            }
        }
        
        // Create new bearish order block - exact Pine Script condition
        if ($this->bottomSwing !== null && 
            $data[$index]['close'] < $this->bottomSwing['y'] && 
            !$this->bottomSwing['crossed']) {
            
            $this->bottomSwing['crossed'] = true;
            
            // Exact Pine Script order block creation logic  
            $useBody = false; // Pine Script has this as false
            $max = $useBody ? max($data[$index - 1]['close'], $data[$index - 1]['open']) : $data[$index - 1]['high'];
            $min = $useBody ? min($data[$index - 1]['close'], $data[$index - 1]['open']) : $data[$index - 1]['low'];
            
            $boxBtm = $min;
            $boxTop = $max;
            $boxLoc = $data[$index - 1]['timestamp'];
            
            // Find the actual order block boundaries - exact Pine Script loop
            for ($i = 1; $i < ($index - $this->bottomSwing['x']) - 1; $i++) {
                $checkIndex = $index - 1 - $i;
                if ($checkIndex < 0 || !isset($data[$checkIndex])) {
                    break;
                }
                
                $currentMax = $useBody ? max($data[$checkIndex]['close'], $data[$checkIndex]['open']) : $data[$checkIndex]['high'];
                $currentMin = $useBody ? min($data[$checkIndex]['close'], $data[$checkIndex]['open']) : $data[$checkIndex]['low'];
                
                if ($currentMax > $boxTop) {
                    $boxTop = $currentMax;
                    $boxBtm = $currentMin;
                    $boxLoc = $data[$checkIndex]['timestamp'];
                }
            }
            
            // Volume calculations - exact Pine Script logic
            $obVolume = 0;
            $obLowVolume = 0;
            $obHighVolume = 0;
            
            // volume + volume[1] + volume[2] in Pine Script
            for ($i = 0; $i <= 2; $i++) {
                $volIndex = $index - $i;
                if ($volIndex >= 0 && isset($data[$volIndex])) {
                    $obVolume += $data[$volIndex]['volume'];
                    if ($i === 2) {
                        $obHighVolume = $data[$volIndex]['volume']; // volume[2] for bearish
                    } else {
                        $obLowVolume += $data[$volIndex]['volume']; // volume + volume[1] for bearish
                    }
                }
            }
            
            // ATR size check - exact Pine Script logic
            $obSize = abs($boxTop - $boxBtm);
            if ($obSize <= $this->atr * self::MAX_ATR_MULT) {
                $newOrderBlock = [
                    'top' => $boxTop,
                    'bottom' => $boxBtm,
                    'obVolume' => $obVolume,
                    'obType' => 'Bear',
                    'startTime' => $boxLoc,
                    'bbVolume' => 0,
                    'obLowVolume' => $obLowVolume,
                    'obHighVolume' => $obHighVolume,
                    'breaker' => false,
                    'breakTime' => null,
                    'timeframeStr' => '',
                    'disabled' => false,
                    'combinedTimeframesStr' => null,
                    'combined' => false
                ];
                
                // Add to beginning of array (unshift in Pine Script)
                array_unshift($this->bearishOrderBlocksList, $newOrderBlock);
                
                // Maintain max order blocks limit
                if (count($this->bearishOrderBlocksList) > self::MAX_ORDER_BLOCKS) {
                    array_pop($this->bearishOrderBlocksList);
                }
            }
        }
    }
    
    /**
     * Calculate ATR (Average True Range) - matches Pine Script ta.atr(10)
     */
    private function calculateATR(array $data, int $index, int $period = 10): void
    {
        if ($index < $period) {
            $this->atr = 0.0;
            return;
        }
        
        $trueRanges = [];
        
        for ($i = max(1, $index - $period + 1); $i <= $index; $i++) {
            if (!isset($data[$i]) || !isset($data[$i - 1])) {
                continue;
            }
            
            $current = $data[$i];
            $previous = $data[$i - 1];
            
            $tr1 = $current['high'] - $current['low'];
            $tr2 = abs($current['high'] - $previous['close']);
            $tr3 = abs($current['low'] - $previous['close']);
            
            $trueRanges[] = max($tr1, $tr2, $tr3);
        }
        
        $this->atr = count($trueRanges) > 0 ? array_sum($trueRanges) / count($trueRanges) : 0.0;
    }
    
    /**
     * Get highest high over a period - matches Pine Script ta.highest(len)
     */
    private function getHighest(array $data, int $index, int $period): float
    {
        $highest = -PHP_FLOAT_MAX;
        
        // Pine Script ta.highest looks at current bar + (period-1) previous bars
        for ($i = $index - $period + 1; $i <= $index; $i++) {
            if ($i >= 0 && isset($data[$i])) {
                $highest = max($highest, $data[$i]['high']);
            }
        }
        
        return $highest === -PHP_FLOAT_MAX ? 0.0 : $highest;
    }
    
    /**
     * Get lowest low over a period - matches Pine Script ta.lowest(len)
     */
    private function getLowest(array $data, int $index, int $period): float
    {
        $lowest = PHP_FLOAT_MAX;
        
        // Pine Script ta.lowest looks at current bar + (period-1) previous bars
        for ($i = $index - $period + 1; $i <= $index; $i++) {
            if ($i >= 0 && isset($data[$i])) {
                $lowest = min($lowest, $data[$i]['low']);
            }
        }
        
        return $lowest === PHP_FLOAT_MAX ? 0.0 : $lowest;
    }
    
    /**
     * Reset service state
     */
    private function resetState(): void
    {
        $this->bullishOrderBlocksList = [];
        $this->bearishOrderBlocksList = [];
        $this->swingHistory = [];
        $this->swingType = 0;
        $this->topSwing = null;
        $this->bottomSwing = null;
        $this->atr = 0.0;
    }
    
    /**
     * Get configuration summary
     */
    public function getConfiguration(): array
    {
        return [
            'swingLength' => $this->swingLength,
            'obEndMethod' => $this->obEndMethod,
            'bullishOrderBlocks' => $this->bullishOrderBlocks,
            'bearishOrderBlocks' => $this->bearishOrderBlocks,
            'combineOBs' => $this->combineOBs,
            'maxATRMult' => self::MAX_ATR_MULT,
            'maxOrderBlocks' => self::MAX_ORDER_BLOCKS
        ];
    }
}