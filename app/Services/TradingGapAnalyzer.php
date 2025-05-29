<?php

namespace App\Services;



class TradingGapAnalyzer {
    
    /**
     * Find the maximum gap between significant trading activities
     * 
     * @param array $tradingData - Array with timestamp keys and trade count values
     * @param int $significantThreshold - Minimum trades to consider as significant activity
     * @param float $percentileThreshold - Percentile threshold for significant activity (0.0 to 1.0)
     * @return array - Analysis results including max gap and statistics
     */
    public function findMaxTradingGap($tradingData, $significantThreshold = 0, $percentileThreshold = 0.75) {
        if (empty($tradingData)) {
            return ['error' => 'No trading data provided'];
        }
      
        
        // Sort by timestamp to ensure chronological order
        ksort($tradingData);
       
        // Find significant trading periods
        $significantPeriods = [];
        foreach ($tradingData as $timestamp => $tradeCount) {
            if ($tradeCount >= $significantThreshold) {
                $significantPeriods[] = $timestamp;
            }
        }


        
        if (count($significantPeriods) < 2) {
            return [
                'error' => 'Not enough significant trading periods found',
                'significant_threshold' => $significantThreshold,
                'significant_periods_count' => count($significantPeriods)
            ];
        }
        // Calculate gaps between significant periods
        $gaps = [];
        for ($i = 1; $i < count($significantPeriods); $i++) {
            $gapMs = $significantPeriods[$i] - $significantPeriods[$i - 1];
            $gaps[] = [
                'start_timestamp' => $significantPeriods[$i - 1],
                'end_timestamp' => $significantPeriods[$i],
                'gap_ms' => $gapMs,
                'gap_minutes' => round($gapMs / (1000 * 60), 2),
                'gap_hours' => round($gapMs / (1000 * 60 * 60), 2),
                'start_date' => date('Y-m-d H:i:s', $significantPeriods[$i - 1] / 1000),
                'end_date' => date('Y-m-d H:i:s', $significantPeriods[$i] / 1000)
            ];
        }
        
        // Find maximum gap
        $maxGap = max(array_column($gaps, 'gap_ms'));
        $maxGapIndex = array_search($maxGap, array_column($gaps, 'gap_ms'));
        
        // Calculate statistics
        $gapMinutes = array_column($gaps, 'gap_minutes');
        $avgGap = array_sum($gapMinutes) / count($gapMinutes);
        $medianGap = $this->calculateMedian($gapMinutes);
        
        return [
            'max_gap' => $gaps[$maxGapIndex],
            'all_gaps' => $gaps,
            'statistics' => [
                'total_gaps' => count($gaps),
                'avg_gap_minutes' => round($avgGap, 2),
                'median_gap_minutes' => round($medianGap, 2),
                'significant_threshold' => $significantThreshold,
                'total_significant_periods' => count($significantPeriods)
            ],
            'recommendation' => $this->generateRecommendation($gaps[$maxGapIndex], $avgGap)
        ];
    }
    
    /**
     * Alternative method: Find gaps based on activity levels
     */
    public function findInactivityPeriods($tradingData, $inactiveThreshold = 0) {
        ksort($tradingData);
        
        $inactivePeriods = [];
        $currentInactiveStart = null;
        
        foreach ($tradingData as $timestamp => $tradeCount) {
            if ($tradeCount <= $inactiveThreshold) {
                if ($currentInactiveStart === null) {
                    $currentInactiveStart = $timestamp;
                }
            } else {
                if ($currentInactiveStart !== null) {
                    $duration = $timestamp - $currentInactiveStart;
                    $inactivePeriods[] = [
                        'start_timestamp' => $currentInactiveStart,
                        'end_timestamp' => $timestamp,
                        'duration_ms' => $duration,
                        'duration_minutes' => round($duration / (1000 * 60), 2),
                        'duration_hours' => round($duration / (1000 * 60 * 60), 2),
                        'start_date' => date('Y-m-d H:i:s', $currentInactiveStart / 1000),
                        'end_date' => date('Y-m-d H:i:s', $timestamp / 1000)
                    ];
                    $currentInactiveStart = null;
                }
            }
        }
        
        if (!empty($inactivePeriods)) {
            $maxInactive = max(array_column($inactivePeriods, 'duration_ms'));
            $maxInactiveIndex = array_search($maxInactive, array_column($inactivePeriods, 'duration_ms'));
            
            return [
                'max_inactive_period' => $inactivePeriods[$maxInactiveIndex],
                'all_inactive_periods' => $inactivePeriods,
                'total_inactive_periods' => count($inactivePeriods)
            ];
        }
        
        return ['message' => 'No significant inactive periods found'];
    }
    
    private function calculatePercentile($array, $percentile) {
        sort($array);
        $index = ceil(count($array) * $percentile) - 1;
        return $array[max(0, $index)];
    }
    
    private function calculateMedian($array) {
        sort($array);
        $count = count($array);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($array[$middle - 1] + $array[$middle]) / 2;
        } else {
            return $array[$middle];
        }
    }
    
    private function generateRecommendation($maxGap, $avgGap) {
        $maxGapMinutes = $maxGap['gap_minutes'];
        
        if ($maxGapMinutes > 60) {
            $hours = round($maxGapMinutes / 60, 1);
            return "Consider disabling trader during gaps longer than {$hours} hours. Maximum observed gap was {$maxGapMinutes} minutes.";
        } else {
            return "Consider disabling trader during gaps longer than " . ceil($maxGapMinutes * 0.8) . " minutes. Maximum observed gap was {$maxGapMinutes} minutes.";
        }
    }

}
