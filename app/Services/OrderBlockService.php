<?php

namespace App\Services;

use App\CommonHelpers;

class OrderBlockService
{
    protected int $pivotLeft;
    protected int $pivotRight;
    protected float $bodyRatioThreshold;
    protected int $liquiditySweepLookback;
    protected bool $expandOrderFlow; // include previous candle into zone
    protected int $maxHistory; // how many candles back to consider for detections

    /**
     * Constructor: tune parameters here or inject via service container.
     */
    public function __construct(
        int $pivotLeft = 3,
        int $pivotRight = 3,
        float $bodyRatioThreshold = 0.70,
        int $liquiditySweepLookback = 10,
        bool $expandOrderFlow = true,
        int $maxHistory = 1000
    ) {
        $this->pivotLeft = $pivotLeft;
        $this->pivotRight = $pivotRight;
        $this->bodyRatioThreshold = $bodyRatioThreshold;
        $this->liquiditySweepLookback = $liquiditySweepLookback;
        $this->expandOrderFlow = $expandOrderFlow;
        $this->maxHistory = $maxHistory;
    }

    /**
     * Main entrypoint.
     *
     * @param array $data  array of candles (0 .. n-1). Each element must contain:
     *                     open, high, low, close, body_size, body_min, body_max, upper_wick, lower_wick, binance_timestamp
     * @param int   $index current index (most recent candle index to use). Must be <= count($data)-1
     *
     * @return array list of order blocks (most recent first)
     */
    public function findOrderBlocks(array $data, int $index): array
    {
        $n = count($data);
        if ($index < 0 || $index >= $n) {
            return [];
        }

        // restrict history window (avoid scanning entire huge array if not needed)
        $start = max(0, $index - $this->maxHistory);

        // 1) detect pivots (swing highs and swing lows) using left/right params
        $swingHighs = []; // list of ['idx'=>int,'high'=>float]
        $swingLows  = []; // list of ['idx'=>int,'low'=>float]

        for ($i = $start + $this->pivotLeft; $i <= $index - $this->pivotRight; $i++) {
            $isHigh = true;
            $isLow  = true;
            $h = $data[$i]['high'];
            $l = $data[$i]['low'];

            // compare left and right candles
            for ($j = $i - $this->pivotLeft; $j <= $i + $this->pivotRight; $j++) {
                if ($j === $i) continue;
                if (!isset($data[$j])) {
                    $isHigh = $isLow = false;
                    break;
                }
                if ($h <= $data[$j]['high']) $isHigh = false;
                if ($l >= $data[$j]['low'])  $isLow = false;
                if (!$isHigh && !$isLow) break;
            }

            if ($isHigh) {
                $swingHighs[] = ['idx' => $i, 'high' => $h];
            }
            if ($isLow) {
                $swingLows[] = ['idx' => $i, 'low' => $l];
            }
        }

        // helper: get most recent swing high / low index prior to a given index
        $lastSwingHighBefore = function (int $idx) use ($swingHighs) {
            for ($k = count($swingHighs) - 1; $k >= 0; $k--) {
                if ($swingHighs[$k]['idx'] < $idx) return $swingHighs[$k];
            }
            return null;
        };
        $lastSwingLowBefore = function (int $idx) use ($swingLows) {
            for ($k = count($swingLows) - 1; $k >= 0; $k--) {
                if ($swingLows[$k]['idx'] < $idx) return $swingLows[$k];
            }
            return null;
        };

        $orderBlocks = [];

        // 2) scan every candle k up to $index (use only historical data)
        //    detect BOS: bullish when close > last swing high's high; bearish when close < last swing low's low
        for ($k = $start + $this->pivotLeft; $k <= $index; $k++) {
            // find most recent completed swing high/low strictly before k
            $lastHigh = $lastSwingHighBefore($k);
            $lastLow  = $lastSwingLowBefore($k);

            // bullish BOS detection
            if ($lastHigh !== null) {
                if ($data[$k]['close'] > $lastHigh['high']) {
                    // candidate candle: use the swing high candle index as the OB candle (safe practical choice)
                    $candidateIdx = $lastHigh['idx'];

                    // ensure candidateIdx < k (it will be)
                    if ($candidateIdx >= $k) continue;

                    // compute body_ratio safely: if size 0 avoid div by zero
                    $size = max(1e-9, $data[$candidateIdx]['high'] - $data[$candidateIdx]['low']);
                    $bodySize = isset($data[$candidateIdx]['body_size']) ? $data[$candidateIdx]['body_size'] : abs($data[$candidateIdx]['close'] - $data[$candidateIdx]['open']);
                    $bodyRatio = $bodySize / $size;

                    // sweep / POI detection:
                    // check if breakout candle (k) extended beyond prior highs inside lookback
                    $swept = false;
                    $lookbackStart = max($start, $k - $this->liquiditySweepLookback);
                    $maxPriorHigh = -INF;
                    for ($t = $lookbackStart; $t < $k; $t++) {
                        if ($data[$t]['high'] > $maxPriorHigh) $maxPriorHigh = $data[$t]['high'];
                    }
                    if ($data[$k]['high'] > $maxPriorHigh && $data[$k]['high'] > $lastHigh['high']) {
                        $swept = true;
                    }

                    // probability label
                    $prob = ($bodyRatio >= $this->bodyRatioThreshold) ? 'high' : 'low';

                    // build top/bottom index depending on expandOrderFlow
                    if ($this->expandOrderFlow && ($candidateIdx - 1) >= 0) {
                        $topIndex = $candidateIdx;            // the OB candle is top (bearish candle before breakout)
                        $bottomIndex = $candidateIdx - 1;    // include previous candle for OF
                    } else {
                        $topIndex = $candidateIdx;
                        $bottomIndex = $candidateIdx;
                    }

                    // sanity: avoid duplicates: if OB for same candidate already exists skip
                    $exists = false;
                    foreach ($orderBlocks as $ob) {
                        if ($ob['candidate_index'] === $candidateIdx && $ob['direction'] === 'bullish') {
                            $exists = true;
                            break;
                        }
                    }
                    if ($exists) continue;

                    $orderBlocks[] = [
                        'direction' => 'bullish',
                        'candidate_index' => $candidateIdx,
                        'timestamp' => $data[$candidateIdx]['binance_timestamp'] ?? ($data[$candidateIdx]['timestamp'] ?? null),
                        'top_price' => $data[$candidateIdx]['body_max'],   // upper bound of OB body
                        'bottom_price' => $data[$candidateIdx]['body_min'], // lower bound of OB body
                        'prob' => $prob,
                        'poi' => $swept,
                        'body_ratio' => round($bodyRatio, 4),
                        'formed_at_index' => $k,
                        'mitigated' => false,
                    ];
                }
            }

            // bearish BOS detection
            if ($lastLow !== null) {
                if ($data[$k]['close'] < $lastLow['low']) {
                    $candidateIdx = $lastLow['idx'];
                    if ($candidateIdx >= $k) continue;

                    $size = max(1e-9, $data[$candidateIdx]['high'] - $data[$candidateIdx]['low']);
                    $bodySize = isset($data[$candidateIdx]['body_size']) ? $data[$candidateIdx]['body_size'] : abs($data[$candidateIdx]['close'] - $data[$candidateIdx]['open']);
                    $bodyRatio = $bodySize / $size;

                    // sweep detection (lookback)
                    $swept = false;
                    $lookbackStart = max($start, $k - $this->liquiditySweepLookback);
                    $minPriorLow = INF;
                    for ($t = $lookbackStart; $t < $k; $t++) {
                        if ($data[$t]['low'] < $minPriorLow) $minPriorLow = $data[$t]['low'];
                    }
                    if ($data[$k]['low'] < $minPriorLow && $data[$k]['low'] < $lastLow['low']) {
                        $swept = true;
                    }

                    $prob = ($bodyRatio >= $this->bodyRatioThreshold) ? 'high' : 'low';

                    if ($this->expandOrderFlow && ($candidateIdx - 1) >= 0) {
                        // for bearish OB, top may be previous candle if we include OF - but to keep consistent use candidate as bottom/top
                        $topIndex = $candidateIdx - 1 >= 0 ? $candidateIdx - 1 : $candidateIdx;
                        $bottomIndex = $candidateIdx;
                    } else {
                        $topIndex = $candidateIdx;
                        $bottomIndex = $candidateIdx;
                    }

                    $exists = false;
                    foreach ($orderBlocks as $ob) {
                        if ($ob['candidate_index'] === $candidateIdx && $ob['direction'] === 'bearish') {
                            $exists = true;
                            break;
                        }
                    }
                    if ($exists) continue;

                    $orderBlocks[] = [
                        'direction' => 'bearish',
                        'candidate_index' => $candidateIdx,
                        'timestamp' => $data[$candidateIdx]['binance_timestamp'] ?? ($data[$candidateIdx]['timestamp'] ?? null),
                        'top_price' => $data[$candidateIdx]['body_max'],   // upper bound of OB body
                        'bottom_price' => $data[$candidateIdx]['body_min'], // lower bound of OB body
                        'prob' => $prob,
                        'poi' => $swept,
                        'body_ratio' => round($bodyRatio, 4),
                        'formed_at_index' => $k,
                        'mitigated' => false,
                    ];
                }
            }
        }

        // 3) Post-process: sort by most recent candidate index descending
        usort($orderBlocks, function ($a, $b) {
            return $b['candidate_index'] <=> $a['candidate_index'];
        });

        // Optionally: limit to last N OBs
        // return array_slice($orderBlocks, 0, 50);

        return $orderBlocks;
    }



    public function findRecentOb($data, $index, $isMitigated = true)
    {
        $obs = $this->findOrderBlocks($data, $index);
        foreach ($obs as $i => $ob) {
            $obs[$i] = $this->checkMitigation($data, $ob, $index);
        }

        if (!empty($obs)) {
            foreach ($obs as $ob) {
                $color = $ob['direction'] === 'bearish' ? 'red' : 'green';
                if ($ob['mitigated'] == $isMitigated) {

                    return [
                        'top' => $ob['top_price'],
                        'bottom' => $ob['bottom_price'],
                        'timestamp_initial' => $ob['timestamp'],
                        'timestamp_mitigated' => $isMitigated ? $ob['mitigated_timestamp'] : null,
                        'index' => $ob['candidate_index'],
                        'type' => $ob['direction'],
                        'color' => $color,
                    ];

                }
            }
        }
        return null;
    }



    /**
     * Helper to mark OB as mitigated if price closed fully through its body (callable later).
     * - $currentIndex is up-to (and including) the last candle to check (no future candles).
     *
     * @param array $data
     * @param array $ob
     * @param int $currentIndex
     * @return bool true if mitigated (and sets 'mitigated' flag on returned ob)
     */
    public function checkMitigation(array $data, array $ob, int $currentIndex): array
    {
        $candidateIdx = $ob['candidate_index'];
        if (!isset($data[$candidateIdx])) return $ob;

        // get body bounds for candidate candle
        $bodyTop = $data[$candidateIdx]['body_max'] ?? max($data[$candidateIdx]['open'], $data[$candidateIdx]['close']);
        $bodyBottom = $data[$candidateIdx]['body_min'] ?? min($data[$candidateIdx]['open'], $data[$candidateIdx]['close']);

        // scan from candidateIdx+1 .. currentIndex for a close that fully enters the body (mitigation rule)
        for ($i = $candidateIdx + 1; $i <= $currentIndex && isset($data[$i]); $i++) {
            $close = $data[$i]['close'];
            if ($ob['direction'] === 'bullish') {
                // mitigated when price closes below or equal to bodyBottom (consumes OB body)
                if ($close <= $bodyBottom) {
                    $ob['mitigated'] = true;
                    $ob['mitigated_at_index'] = $i;
                    $ob['mitigated_timestamp'] = $data[$i]['binance_timestamp'] ?? ($data[$i]['timestamp'] ?? null);
                    return $ob;
                }
            } else {
                // bearish: mitigated when price closes above or equal to bodyTop
                if ($close >= $bodyTop) {
                    $ob['mitigated'] = true;
                    $ob['mitigated_at_index'] = $i;
                    $ob['mitigated_timestamp'] = $data[$i]['binance_timestamp'] ?? ($data[$i]['timestamp'] ?? null);
                    return $ob;
                }
            }
        }

        // not mitigated
        $ob['mitigated'] = false;
        return $ob;
    }
}
