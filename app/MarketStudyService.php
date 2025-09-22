<?php

namespace App\Services\Trading;

use Closure;
use InvalidArgumentException;

class MarketStudyService
{
    protected array $data;      // array of candles (same structure you provided)
    protected int $index;       // current candle index
    protected array $config;

    public function __construct(array $data, int $index, array $config = [])
    {
        $this->data = $data;
        $this->index = $index;
        $this->config = array_merge($this->defaultConfig(), $config);
    }

    protected function defaultConfig(): array
    {
        return [
            'lookback' => 1000,                // how many candles back to scan
            'lookahead' => 24,                 // how many candles after an entry to evaluate outcome
            'atr_multiplier_target' => 1.0,    // target = entry + ATR * multiplier
            'atr_multiplier_stop' => 1.5,      // stop = entry - ATR * multiplier (opposite sign per side)
            'min_occurrences' => 5,            // only report strategies/zones with >= this occurrences
            'pivot_lr_points' => 6,            // number of pivot points for line fit (if available)
            'pivot_check_left' => 3,           // pivot left candles
            'pivot_check_right' => 3,          // pivot right candles
            'zone_padding' => 0.0,             // padding for zone boundaries (fraction of ATR)
            'double_breakout_retest_bars' => 6,// how many bars allowed for retest in double breakout
            'single_breakout_aggression_bars' => 3,
        ];
    }

    //
    // PUBLIC: main entry
    //
    public function analyze(): array
    {
        $start = max(0, $this->index - $this->config['lookback']);
        $end   = $this->index; // we don't need future candles beyond current for scanning occurrences

        $results = [
            'strategies' => [
                'trendline' => $this->scanTrendline($start, $end),
                'fvg'       => $this->scanFVG($start, $end),
                'double'    => $this->scanDoubleBreakout($start, $end),
                'single'    => $this->scanSingleBreakout($start, $end),
            ],
            'zones' => $this->scanZones($start, $end),
            'summary' => [],
        ];

        // Build summary + ranked recommendations
        $results['summary'] = $this->buildSummary($results);

        return $results;
    }

    //
    // Helpers: scanning each strategy
    //
    protected function scanTrendline(int $start, int $end): array
    {
        // We'll find candidate trendlines by looking for pivot groups and doing linear regression across pivot points.
        // For each time the price breaks the trendline, we evaluate whether the subsequent lookahead moved in the break direction
        $occurrences = [];

        for ($i = $start + 10; $i <= $end - 1; $i++) {
            // find pivot points ending near i (simple pivot detection)
            $pivots = $this->findPivotsAround($i, $this->config['pivot_check_left'], $this->config['pivot_check_right']);

            if (count($pivots['highs']) + count($pivots['lows']) < 3) continue;

            // build a line from pivot highs OR pivot lows depending on direction (we'll test both)
            foreach (['resistance' => $pivots['highs'], 'support' => $pivots['lows']] as $type => $pts) {
                if (count($pts) < 2) continue;
                [$m, $c] = $this->linearRegressionFromPoints($pts);
                // compute expected line value at candle $i
                $linePrice = $m * $i + $c;
                $close = $this->data[$i]['close'] ?? null;
                if ($close === null) continue;

                // detect a "break": close crosses line significantly (use small tolerance by ATR)
                $atr = $this->data[$i]['atr14'] ?? $this->estimateAtrAt($i);
                $tol = max(1, ($atr ?? 1) * 0.2);

                if ($type === 'resistance' && $close > $linePrice + $tol) {
                    // bullish break
                    $occurrences[] = $this->evaluateOutcome($i, 'long', $atr, $linePrice, 'trendline');
                } elseif ($type === 'support' && $close < $linePrice - $tol) {
                    // bearish break
                    $occurrences[] = $this->evaluateOutcome($i, 'short', $atr, $linePrice, 'trendline');
                }
            }
        }

        return $this->aggregateOccurrences($occurrences);
    }

    protected function scanFVG(int $start, int $end): array
    {
        // FVG definition (assumption): three-candle pattern where middle candle body leaves a gap vs prior/next.
        $occurrences = [];
        for ($i = $start + 3; $i <= $end - 1; $i++) {
            // middle candle index = $i-1
            $mid = $i - 1;
            if ($this->isFVGAt($mid)) {
                // direction: if gap favors long, we look for breakout above gap; else short
                $direction = $this->fvgDirectionAt($mid);
                $atr = $this->data[$mid]['atr14'] ?? $this->estimateAtrAt($mid);
                $occurrences[] = $this->evaluateOutcome($mid, $direction === 'long' ? 'long' : 'short', $atr, null, 'fvg');
            }
        }

        return $this->aggregateOccurrences($occurrences);
    }

    protected function scanDoubleBreakout(int $start, int $end): array
    {
        // double breakout: zone formed (pivot wick) -> breakout -> retest -> breakout again -> entry at previous high
        $occurrences = [];
        // We'll build zones by larger-window pivots (e.g., local highs over 12 bars). This is a simplification.
        $zones = $this->buildZones($start, $end);

        foreach ($zones as $zone) {
            $firstBreakIndex = $zone['first_break_index'] ?? null;
            $retestIndex = $zone['retest_index'] ?? null;
            $secondBreakIndex = $zone['second_break_index'] ?? null;
            if ($firstBreakIndex !== null && $retestIndex !== null && $secondBreakIndex !== null) {
                // entry at previous high (we'll use secondBreakIndex as entry)
                $atr = $this->data[$secondBreakIndex]['atr14'] ?? $this->estimateAtrAt($secondBreakIndex);
                $occurrences[] = $this->evaluateOutcome($secondBreakIndex, $zone['direction'] === 'long' ? 'long' : 'short', $atr, $zone['entry_price'] ?? null, 'double');
            }
        }

        return $this->aggregateOccurrences($occurrences);
    }

    protected function scanSingleBreakout(int $start, int $end): array
    {
        // single breakout: aggressive entry on first breakout of zone
        $occurrences = [];
        $zones = $this->buildZones($start, $end);
        foreach ($zones as $zone) {
            if (!empty($zone['first_break_index'])) {
                $idx = $zone['first_break_index'];
                $atr = $this->data[$idx]['atr14'] ?? $this->estimateAtrAt($idx);
                $occurrences[] = $this->evaluateOutcome($idx, $zone['direction'] === 'long' ? 'long' : 'short', $atr, $zone['entry_price'] ?? null, 'single');
            }
        }

        return $this->aggregateOccurrences($occurrences);
    }

    //
    // Zones: find zones and compute effectiveness
    //
    protected function scanZones(int $start, int $end): array
    {
        $zones = $this->buildZones($start, $end);
        $zoneStats = [];
        foreach ($zones as $z) {
            $zoneStats[] = [
                'zone' => $z,
                'stats' => $this->evaluateZoneEffectiveness($z)
            ];
        }
        // sort by occurrences / winrate combination
        usort($zoneStats, function ($a, $b) {
            $ar = ($a['stats']['occurrences'] ?? 0) * ($a['stats']['win_rate'] ?? 0);
            $br = ($b['stats']['occurrences'] ?? 0) * ($b['stats']['win_rate'] ?? 0);
            return $br <=> $ar;
        });

        return $zoneStats;
    }

    //
    // ----- Utility detection & evaluation functions -----
    //

    protected function findPivotsAround(int $i, int $left, int $right): array
    {
        // simple pivot finder: find local highs/lows with left/right checks
        $highs = [];
        $lows  = [];
        $n = count($this->data);
        for ($idx = max(0, $i - 50); $idx <= min($n - 1, $i + 50); $idx++) {
            $isHigh = true; $isLow = true;
            $price = $this->data[$idx]['high'] ?? null;
            $lowPrice = $this->data[$idx]['low'] ?? null;
            if ($price === null || $lowPrice === null) continue;
            for ($l = 1; $l <= $left; $l++) {
                if (!isset($this->data[$idx - $l])) { $isHigh = false; $isLow = false; break; }
                if ($this->data[$idx - $l]['high'] >= $price) $isHigh = false;
                if ($this->data[$idx - $l]['low'] <= $lowPrice) $isLow = false;
            }
            for ($r = 1; $r <= $right; $r++) {
                if (!isset($this->data[$idx + $r])) { $isHigh = false; $isLow = false; break; }
                if ($this->data[$idx + $r]['high'] > $price) $isHigh = false;
                if ($this->data[$idx + $r]['low'] < $lowPrice) $isLow = false;
            }
            if ($isHigh) $highs[] = ['x' => $idx, 'y' => $price];
            if ($isLow)  $lows[]  = ['x' => $idx, 'y' => $lowPrice];
        }
        return ['highs' => $highs, 'lows' => $lows];
    }

    protected function linearRegressionFromPoints(array $points): array
    {
        // points: array of ['x'=>idx, 'y'=>price]
        $n = count($points);
        if ($n === 0) return [0, 0];
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($points as $p) {
            $x = $p['x']; $y = $p['y'];
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
        }
        $m = ($n * $sumXY - $sumX * $sumY) / max(1e-9, ($n * $sumXX - $sumX * $sumX));
        $c = ($sumY - $m * $sumX) / $n;
        return [$m, $c];
    }

    protected function estimateAtrAt(int $i)
    {
        return $this->data[$i]['atr14'] ?? 0;
    }

    protected function isFVGAt(int $midIndex): bool
    {
        // FVG detection: we assume candles at midIndex-1, midIndex, midIndex+1 produce a body gap
        if (!isset($this->data[$midIndex - 1], $this->data[$midIndex], $this->data[$midIndex + 1])) return false;
        $a = $this->data[$midIndex - 1]; // prev
        $b = $this->data[$midIndex];     // mid
        $c = $this->data[$midIndex + 1]; // next

        // FVG long: previous high < next low (gap up) OR bodies leave gap
        $prevHigh = max($a['open'], $a['close']);
        $nextLow  = min($c['open'], $c['close']);
        $prevLow  = min($a['open'], $a['close']);
        $nextHigh = max($c['open'], $c['close']);

        // consider body gap ignoring tiny overlaps
        if ($nextLow > $prevHigh + 0.0) return true;
        if ($nextHigh < $prevLow - 0.0) return true;

        return false;
    }

    protected function fvgDirectionAt(int $midIndex): string
    {
        // decide direction by whether gap is up or down
        $a = $this->data[$midIndex - 1];
        $c = $this->data[$midIndex + 1];
        $prevHigh = max($a['open'], $a['close']);
        $nextLow = min($c['open'], $c['close']);
        if ($nextLow > $prevHigh) return 'long';
        return 'short';
    }

    protected function buildZones(int $start, int $end): array
    {
        // Build simple zones from local pivot wicks (approximation of 1h pivots using larger window).
        $zones = [];
        $window = 12; // approximate higher timeframe; adjust externally if needed
        for ($i = $start + $window; $i <= $end - $window; $i++) {
            // detect pivot high
            $isHigh = true; $isLow = true;
            for ($d = 1; $d <= $window; $d++) {
                if ($this->data[$i - $d]['high'] > $this->data[$i]['high']) $isHigh = false;
                if ($this->data[$i + $d]['high'] > $this->data[$i]['high']) $isHigh = false;
                if ($this->data[$i - $d]['low'] < $this->data[$i]['low']) $isLow = false;
                if ($this->data[$i + $d]['low'] < $this->data[$i]['low']) $isLow = false;
            }
            if ($isHigh) {
                $zoneTop = $this->data[$i]['high'];
                $zoneBottom = $this->data[$i]['low'];
                $direction = 'short';
            } elseif ($isLow) {
                $zoneTop = $this->data[$i]['high'];
                $zoneBottom = $this->data[$i]['low'];
                $direction = 'long';
            } else continue;

            // search for break -> retest -> second break pattern after pivot
            $firstBreak = $this->findFirstBreak($i, $end, $direction, $zoneTop, $zoneBottom);
            if ($firstBreak === null) continue;
            $retest = $this->findRetestAfter($firstBreak['index'], $end, $direction, $zoneTop, $zoneBottom, $this->config['double_breakout_retest_bars']);
            if ($retest === null) continue;
            $secondBreak = $this->findFirstBreak($retest['index'], $end, $direction, $zoneTop, $zoneBottom, 1);
            // single breakout will use firstBreak only
            $zones[] = [
                'pivot_index' => $i,
                'zone_top' => $zoneTop,
                'zone_bottom' => $zoneBottom,
                'direction' => $direction,
                'first_break_index' => $firstBreak['index'],
                'first_break_price' => $firstBreak['price'],
                'retest_index' => $retest['index'],
                'retest_price' => $retest['price'],
                'second_break_index' => $secondBreak['index'] ?? null,
                'entry_price' => $secondBreak['price'] ?? $firstBreak['price'],
            ];
        }
        return $zones;
    }

    protected function findFirstBreak(int $startIndex, int $end, string $direction, $zoneTop, $zoneBottom, $maxBars = 24)
    {
        $limit = min($end, $startIndex + $maxBars);
        for ($i = $startIndex + 1; $i <= $limit; $i++) {
            $close = $this->data[$i]['close'] ?? null;
            if ($close === null) continue;
            if ($direction === 'long' && $close > $zoneTop) {
                return ['index' => $i, 'price' => $close];
            } elseif ($direction === 'short' && $close < $zoneBottom) {
                return ['index' => $i, 'price' => $close];
            }
        }
        return null;
    }

    protected function findRetestAfter(int $breakIndex, int $end, string $direction, $zoneTop, $zoneBottom, $maxBars)
    {
        $limit = min($end, $breakIndex + $maxBars);
        for ($i = $breakIndex + 1; $i <= $limit; $i++) {
            $low = $this->data[$i]['low'] ?? null;
            $high = $this->data[$i]['high'] ?? null;
            if ($low === null || $high === null) continue;
            // If long: look for a bar that touches/comes back into zone (retest)
            if ($direction === 'long' && $low <= $zoneTop && $high >= $zoneTop) {
                return ['index' => $i, 'price' => $this->data[$i]['close']];
            }
            if ($direction === 'short' && $high >= $zoneBottom && $low <= $zoneBottom) {
                return ['index' => $i, 'price' => $this->data[$i]['close']];
            }
        }
        return null;
    }

    protected function evaluateOutcome(int $entryIndex, string $side, $atr, $entryPrice = null, $strategy = 'unknown'): array
    {
        $n = count($this->data);
        $lookahead = $this->config['lookahead'];
        $end = min($n - 1, $entryIndex + $lookahead);

        $entryPrice = $entryPrice ?? $this->data[$entryIndex]['close'] ?? null;
        if ($entryPrice === null) {
            return ['strategy' => $strategy, 'entry' => $entryIndex, 'result' => null];
        }

        $target = $side === 'long'
            ? $entryPrice + ($atr * $this->config['atr_multiplier_target'])
            : $entryPrice - ($atr * $this->config['atr_multiplier_target']);

        $stop = $side === 'long'
            ? $entryPrice - ($atr * $this->config['atr_multiplier_stop'])
            : $entryPrice + ($atr * $this->config['atr_multiplier_stop']);

        $bestMove = 0.0;
        $hitTarget = false;
        $hitStop = false;
        $barsToOutcome = null;

        for ($i = $entryIndex + 1; $i <= $end; $i++) {
            $high = $this->data[$i]['high'] ?? null;
            $low  = $this->data[$i]['low'] ?? null;
            if ($high === null || $low === null) continue;

            if ($side === 'long') {
                if ($high >= $target) {
                    $hitTarget = true;
                    $barsToOutcome = $i - $entryIndex;
                    break;
                }
                if ($low <= $stop) {
                    $hitStop = true;
                    $barsToOutcome = $i - $entryIndex;
                    break;
                }
                $bestMove = max($bestMove, ($high - $entryPrice) / max(1e-9, $entryPrice));
            } else {
                if ($low <= $target) {
                    $hitTarget = true;
                    $barsToOutcome = $i - $entryIndex;
                    break;
                }
                if ($high >= $stop) {
                    $hitStop = true;
                    $barsToOutcome = $i - $entryIndex;
                    break;
                }
                $bestMove = max($bestMove, ($entryPrice - $low) / max(1e-9, $entryPrice));
            }
        }

        // if neither hit, compute final unrealized move at end
        if (!$hitTarget && !$hitStop) {
            $lastClose = $this->data[$end]['close'] ?? $entryPrice;
            $bestMove = $side === 'long'
                ? max($bestMove, ($lastClose - $entryPrice) / max(1e-9, $entryPrice))
                : max($bestMove, ($entryPrice - $lastClose) / max(1e-9, $entryPrice));
        }

        return [
            'strategy' => $strategy,
            'entry' => $entryIndex,
            'side' => $side,
            'entry_price' => $entryPrice,
            'atr' => $atr,
            'target' => $target,
            'stop' => $stop,
            'hit_target' => $hitTarget,
            'hit_stop' => $hitStop,
            'best_move_pct' => $bestMove,
            'bars_to_outcome' => $barsToOutcome,
        ];
    }

    protected function aggregateOccurrences(array $occurrences): array
    {
        $total = count($occurrences);
        if ($total === 0) {
            return ['occurrences' => 0, 'win_rate' => 0.0, 'avg_move_pct' => 0.0, 'avg_bars' => null, 'raw' => []];
        }
        $wins = 0; $sumMove = 0; $sumBars = 0; $barsCount = 0;
        foreach ($occurrences as $o) {
            if (empty($o)) continue;
            if (!empty($o['hit_target']) && $o['hit_target']) $wins++;
            $sumMove += ($o['best_move_pct'] ?? 0);
            if (!empty($o['bars_to_outcome'])) { $sumBars += $o['bars_to_outcome']; $barsCount++; }
        }
        return [
            'occurrences' => $total,
            'win_rate' => $total ? $wins / $total : 0,
            'avg_move_pct' => $total ? $sumMove / $total : 0,
            'avg_bars' => $barsCount ? ($sumBars / $barsCount) : null,
            'raw' => $occurrences,
        ];
    }

    protected function evaluateZoneEffectiveness(array $zone): array
    {
        // Count how many times this zone produced continuation vs reversal (look for entries around zone events)
        // We'll scan for bars that touched zone and then measure subsequent move direction
        $touches = [];
        $n = count($this->data);
        $start = max(0, $zone['pivot_index'] - 50);
        $end = min($n - 1, $zone['pivot_index'] + $this->config['lookback']);

        for ($i = $start; $i <= $end; $i++) {
            $low = $this->data[$i]['low'] ?? null;
            $high = $this->data[$i]['high'] ?? null;
            if ($low === null || $high === null) continue;
            // touched if overlap
            if ($high >= $zone['zone_bottom'] && $low <= $zone['zone_top']) {
                // after-touch, measure 12 bars outcome
                $outcome = $this->evaluateOutcome($i, $zone['direction'] === 'long' ? 'long' : 'short', $this->data[$i]['atr14'] ?? $this->estimateAtrAt($i), null, 'zone-touch');
                $touches[] = $outcome;
            }
        }

        $agg = $this->aggregateOccurrences($touches);
        return $agg;
    }

    protected function buildSummary(array $results): array
    {
        // Rank strategies by a simple score: win_rate * occurrences * avg_move_pct
        $scores = [];
        foreach ($results['strategies'] as $name => $s) {
            $score = ($s['win_rate'] ?? 0) * ($s['occurrences'] ?? 0) * max(1e-6, ($s['avg_move_pct'] ?? 0));
            $scores[$name] = [
                'metrics' => $s,
                'score' => $score,
            ];
        }
        arsort($scores);
        $ranking = array_keys($scores);
        $best = reset($ranking);

        // pick top zones
        $topZones = array_slice($results['zones'], 0, 5);

        return [
            'ranking' => $scores,
            'recommended_strategy' => $best,
            'top_zones' => $topZones,
            'notes' => [
                'lookback_used' => $this->config['lookback'],
                'lookahead_used' => $this->config['lookahead']
            ],
        ];
    }
}
