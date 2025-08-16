<?php

namespace App\Services;




class OrderBlockDetector
{
    private $mslen = 5; // Market structure length (equivalent to mslen in Pine Script)
    private $atrPeriod = 200; // ATR calculation period
    private $obmode = "Length"; // "Length" or "Full"
    private $len = 5; // Length parameter for order block construction

    public function __construct($mslen = 5, $atrPeriod = 200, $obmode = "Length", $len = 5)
    {
        $this->mslen = $mslen;
        $this->atrPeriod = $atrPeriod;
        $this->obmode = $obmode;
        $this->len = $len;
    }

    /**
     * Get recent order blocks up to the specified candle index
     * 
     * @param array $data - Array of candlestick data
     * @param int $index - Current index to analyze up to
     * @param int $maxOrderBlocks - Maximum number of order blocks to return (default: 5)
     * @return array - Array of order blocks with bull/bear classification
     */
    public function getRecentOrderBlocks($data, $index, $maxOrderBlocks = 5)
    {
        if ($index < $this->mslen * 2 + $this->atrPeriod) {
            return ['bull' => [], 'bear' => []]; // Not enough data
        }

        // Initialize market structure state
        $ms = $this->initializeMarketStructure($data, $index);

        // Calculate ATR for order block positioning
        $atr = $this->calculateATR($data, $index);

        // Find order blocks
        $bullOrderBlocks = [];
        $bearOrderBlocks = [];

        // Track market structure changes and identify order block formation points
        $structureChanges = $this->findStructureChanges($data, $index);

        foreach ($structureChanges as $change) {
            if ($change['type'] === 'choch_bull_to_bear') {
                // Bullish order block formed
                $ob = $this->createBullOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bullOrderBlocks[] = $ob;
                }
            } elseif ($change['type'] === 'choch_bear_to_bull') {
                // Bearish order block formed  
                $ob = $this->createBearOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bearOrderBlocks[] = $ob;
                }
            }
        }

        // Sort by recency and limit results
        usort($bullOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });

        usort($bearOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });


        $bullZone = [];
        $bearZone = [];
        foreach (array_slice($bullOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bullZone[] = $zone;
            }
        }

        foreach (array_slice($bearOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bearZone[] = $zone;
            }
        }

        return [
            'bull' => $bullZone,
            'bear' => $bearZone
        ];
    }

    private function initializeMarketStructure($data, $index)
    {
        return [
            'trend' => 0, // 1 for uptrend, -1 for downtrend, 0 for initial
            'start' => 0, // Market structure state
            'choch' => null, // Change of character level
            'bos' => null, // Break of structure level
            'main' => null, // Current main level being tracked
            'loc' => 0, // Location of last structure change
            'temp' => 0, // Temporary location tracker
        ];
    }

    private function calculateATR($data, $index, $period = null)
    {
        if ($period === null) {
            $period = min($this->atrPeriod, $index);
        }

        $trValues = [];
        $startIdx = max(1, $index - $period + 1);

        for ($i = $startIdx; $i <= $index; $i++) {
            $high = $data[$i]['high'];
            $low = $data[$i]['low'];
            $prevClose = $data[$i - 1]['close'];

            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            $trValues[] = $tr;
        }

        return array_sum($trValues) / count($trValues);
    }

    private function findStructureChanges($data, $index)
    {
        $changes = [];
        $ms = $this->initializeMarketStructure($data, $index);

        // Find pivot highs and lows
        $pivots = $this->findPivots($data, $index);

        // Analyze structure changes
        $trend = 0; // 0 = initial, 1 = up, -1 = down
        $lastHigh = null;
        $lastLow = null;

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            $current = $data[$i];

            // Check for pivot highs and lows
            if ($this->isPivotHigh($data, $i)) {
                if ($lastHigh !== null && $current['high'] < $lastHigh) {
                    // Lower high - potential trend change to bearish
                    if ($trend === 1) {
                        $changes[] = [
                            'type' => 'choch_bull_to_bear',
                            'index' => $i,
                            'price' => $current['high'],
                            'timestamp' => $current['timestamp'],
                            'prev_high' => $lastHigh
                        ];
                        $trend = -1;
                    }
                } elseif ($lastHigh !== null && $current['high'] > $lastHigh) {
                    // Higher high - continuation or new bullish trend
                    if ($trend !== 1) {
                        $trend = 1;
                    }
                }
                $lastHigh = $current['high'];
            }

            if ($this->isPivotLow($data, $i)) {
                if ($lastLow !== null && $current['low'] > $lastLow) {
                    // Higher low - potential trend change to bullish
                    if ($trend === -1) {
                        $changes[] = [
                            'type' => 'choch_bear_to_bull',
                            'index' => $i,
                            'price' => $current['low'],
                            'timestamp' => $current['timestamp'],
                            'prev_low' => $lastLow
                        ];
                        $trend = 1;
                    }
                } elseif ($lastLow !== null && $current['low'] < $lastLow) {
                    // Lower low - continuation or new bearish trend
                    if ($trend !== -1) {
                        $trend = -1;
                    }
                }
                $lastLow = $current['low'];
            }
        }

        return $changes;
    }

    private function findPivots($data, $index)
    {
        $pivots = ['highs' => [], 'lows' => []];

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            if ($this->isPivotHigh($data, $i)) {
                $pivots['highs'][] = ['index' => $i, 'price' => $data[$i]['high']];
            }
            if ($this->isPivotLow($data, $i)) {
                $pivots['lows'][] = ['index' => $i, 'price' => $data[$i]['low']];
            }
        }

        return $pivots;
    }

    private function isPivotHigh($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentHigh = $data[$index]['high'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        // Check right side  
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        return true;
    }

    private function isPivotLow($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentLow = $data[$index]['low'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        // Check right side
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        return true;
    }

    private function createBullOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the highest point before the structure change
        $highestIdx = $this->findHighest($data, max(0, $idx - 20), $idx);
        if ($highestIdx === null) return null;

        $candle = $data[$highestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust bottom based on mode
        if ($this->obmode === "Length") {
            $adjustedBottom = $candle['low'] + ($atr / (5 / $this->len));
            $bottom = min($adjustedBottom, $candle['high']) > $candle['low'] ? $candle['low'] : $adjustedBottom;
        }

        return [
            'type' => 'bull',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $highestIdx,
            'timestamp' => $candle['binance_timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function createBearOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the lowest point before the structure change  
        $lowestIdx = $this->findLowest($data, max(0, $idx - 20), $idx);
        if ($lowestIdx === null) return null;

        $candle = $data[$lowestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust top based on mode
        if ($this->obmode === "Length") {
            $adjustedTop = $candle['high'] - ($atr / (5 / $this->len));
            $top = max($adjustedTop, $candle['low']) < $candle['high'] ? $candle['high'] : $adjustedTop;
        }

        return [
            'type' => 'bear',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $lowestIdx,
            'timestamp' => $candle['binance_timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function findHighest($data, $startIdx, $endIdx)
    {
        $highest = null;
        $highestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($highest === null || $data[$i]['high'] > $highest) {
                $highest = $data[$i]['high'];
                $highestIdx = $i;
            }
        }

        return $highestIdx;
    }

    private function findLowest($data, $startIdx, $endIdx)
    {
        $lowest = null;
        $lowestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($lowest === null || $data[$i]['low'] < $lowest) {
                $lowest = $data[$i]['low'];
                $lowestIdx = $i;
            }
        }

        return $lowestIdx;
    }
}

