<?php

namespace App;

class DivergenceStrategyService
{
    // Configuration parameters
    private $lookback = 20;
    private $minBarsBetween = 10;
    private $priceTolerance = 0.5; // percentage
    private $rsiLength = 14; // Using rsi6 from your data
    private $emaFast = 12; // Using ema12 from your data
    private $emaSlow = 26; // Using ema26 from your data
    private $maxSlPercent = 2.0;

    // Storage for peaks and valleys
    private $peaks = [];
    private $valleys = [];

    public function handleOpeningConditions($data, $index)
    {
        // Need at least enough data for analysis
        if ($index < $this->lookback * 2) {
            return null;
        }

        // Update peaks and valleys
        $this->updatePeaksAndValleys($data, $index);

        // Check for double top/bottom patterns
        $doubleTop = $this->detectDoubleTop($data, $index);
        $doubleBottom = $this->detectDoubleBottom($data, $index);

        // Check RSI divergence
        $bullishDivergence = $this->checkBullishRSIDivergence($data, $index);
        $bearishDivergence = $this->checkBearishRSIDivergence($data, $index);

        // Check EMA trend
        $currentCandle = $data[$index];
        $bullishTrend = $currentCandle['ema12'] > $currentCandle['ema26'];
        $bearishTrend = $currentCandle['ema12'] < $currentCandle['ema26'];

        // Long condition: Double bottom + Bullish RSI divergence + Bullish EMA trend
        if ($doubleBottom && $bullishDivergence && $bullishTrend) {
            return 'LONG';
        }

        // Short condition: Double top + Bearish RSI divergence + Bearish EMA trend
        if ($doubleTop && $bearishDivergence && $bearishTrend) {
            return 'SHORT';
        }

        return null;
    }

    private function updatePeaksAndValleys($data, $index)
    {
        // Only check for peaks/valleys if we have enough data
        if ($index < $this->lookback) {
            return;
        }

        // Check for peak (pivot high)
        $checkIndex = $index - $this->lookback;
        if ($this->isPivotHigh($data, $checkIndex)) {
            $this->peaks[] = [
                'index' => $checkIndex,
                'price' => $data[$checkIndex]['high'],
                'timestamp' => $data[$checkIndex]['timestamp']
            ];

            // Keep only recent peaks
            $this->peaks = array_slice($this->peaks, -10);
        }

        // Check for valley (pivot low)
        if ($this->isPivotLow($data, $checkIndex)) {
            $this->valleys[] = [
                'index' => $checkIndex,
                'price' => $data[$checkIndex]['low'],
                'timestamp' => $data[$checkIndex]['timestamp']
            ];

            // Keep only recent valleys
            $this->valleys = array_slice($this->valleys, -10);
        }
    }

    private function isPivotHigh($data, $index)
    {
        if ($index < $this->lookback || $index >= count($data) - $this->lookback) {
            return false;
        }

        $currentHigh = $data[$index]['high'];

        // Check left side
        for ($i = $index - $this->lookback; $i < $index; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        // Check right side
        for ($i = $index + 1; $i <= $index + $this->lookback; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        return true;
    }

    private function isPivotLow($data, $index)
    {
        if ($index < $this->lookback || $index >= count($data) - $this->lookback) {
            return false;
        }

        $currentLow = $data[$index]['low'];

        // Check left side
        for ($i = $index - $this->lookback; $i < $index; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        // Check right side
        for ($i = $index + 1; $i <= $index + $this->lookback; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        return true;
    }

    private function detectDoubleTop($data, $index)
    {
        if (count($this->peaks) < 2) {
            return false;
        }

        // Get the last two peaks
        $lastPeak = end($this->peaks);
        $secondLastPeak = $this->peaks[count($this->peaks) - 2];

        // Check if peaks are recent enough
        if ($index - $lastPeak['index'] > $this->lookback * 2) {
            return false;
        }

        // Check price similarity
        $priceDiff = abs($lastPeak['price'] - $secondLastPeak['price']);
        $priceToleranceAmount = $secondLastPeak['price'] * ($this->priceTolerance / 100);

        if ($priceDiff > $priceToleranceAmount) {
            return false;
        }

        // Check minimum bars between peaks
        $barsBetween = $lastPeak['index'] - $secondLastPeak['index'];
        if ($barsBetween < $this->minBarsBetween) {
            return false;
        }

        return true;
    }

    private function detectDoubleBottom($data, $index)
    {
        if (count($this->valleys) < 2) {
            return false;
        }

        // Get the last two valleys
        $lastValley = end($this->valleys);
        $secondLastValley = $this->valleys[count($this->valleys) - 2];

        // Check if valleys are recent enough
        if ($index - $lastValley['index'] > $this->lookback * 2) {
            return false;
        }

        // Check price similarity
        $priceDiff = abs($lastValley['price'] - $secondLastValley['price']);
        $priceToleranceAmount = $secondLastValley['price'] * ($this->priceTolerance / 100);

        if ($priceDiff > $priceToleranceAmount) {
            return false;
        }

        // Check minimum bars between valleys
        $barsBetween = $lastValley['index'] - $secondLastValley['index'];
        if ($barsBetween < $this->minBarsBetween) {
            return false;
        }

        return true;
    }

    private function checkBullishRSIDivergence($data, $index)
    {
        if (count($this->valleys) < 2) {
            return false;
        }

        $lastValley = end($this->valleys);
        $secondLastValley = $this->valleys[count($this->valleys) - 2];

        // Check if we have RSI data at those points
        if (!isset($data[$lastValley['index']]['rsi6']) || !isset($data[$secondLastValley['index']]['rsi6'])) {
            return false;
        }

        // Price made lower low
        $priceLowerLow = $lastValley['price'] < $secondLastValley['price'];

        // RSI made higher low
        $rsiHigherLow = $data[$lastValley['index']]['rsi6'] > $data[$secondLastValley['index']]['rsi6'];

        return $priceLowerLow && $rsiHigherLow;
    }

    private function checkBearishRSIDivergence($data, $index)
    {
        if (count($this->peaks) < 2) {
            return false;
        }

        $lastPeak = end($this->peaks);
        $secondLastPeak = $this->peaks[count($this->peaks) - 2];

        // Check if we have RSI data at those points
        if (!isset($data[$lastPeak['index']]['rsi6']) || !isset($data[$secondLastPeak['index']]['rsi6'])) {
            return false;
        }

        // Price made higher high
        $priceHigherHigh = $lastPeak['price'] > $secondLastPeak['price'];

        // RSI made lower high
        $rsiLowerHigh = $data[$lastPeak['index']]['rsi6'] < $data[$secondLastPeak['index']]['rsi6'];

        return $priceHigherHigh && $rsiLowerHigh;
    }

    // Optional: Get stop loss and take profit levels for position sizing
    public function getStopLossLevel($data, $index, $direction)
    {
        $currentPrice = $data[$index]['close'];

        if ($direction === 'LONG' && !empty($this->valleys)) {
            $lastValley = end($this->valleys);
            $slDistance = $currentPrice - $lastValley['price'];
            $slPercent = ($slDistance / $currentPrice) * 100;

            if ($slPercent <= $this->maxSlPercent) {
                return $lastValley['price'];
            } else {
                return $currentPrice * (1 - $this->maxSlPercent / 100);
            }
        }

        if ($direction === 'SHORT' && !empty($this->peaks)) {
            $lastPeak = end($this->peaks);
            $slDistance = $lastPeak['price'] - $currentPrice;
            $slPercent = ($slDistance / $currentPrice) * 100;

            if ($slPercent <= $this->maxSlPercent) {
                return $lastPeak['price'];
            } else {
                return $currentPrice * (1 + $this->maxSlPercent / 100);
            }
        }

        return null;
    }

    public function getTakeProfitLevel($data, $index, $direction, $riskRewardRatio = 3.0)
    {
        $currentPrice = $data[$index]['close'];
        $stopLoss = $this->getStopLossLevel($data, $index, $direction);

        if ($stopLoss === null) {
            return null;
        }

        if ($direction === 'LONG') {
            $slDistance = $currentPrice - $stopLoss;
            return $currentPrice + ($slDistance * $riskRewardRatio);
        } else {
            $slDistance = $stopLoss - $currentPrice;
            return $currentPrice - ($slDistance * $riskRewardRatio);
        }
    }

    // Configuration setters
    public function setLookback($lookback)
    {
        $this->lookback = $lookback;
    }
    public function setMinBarsBetween($bars)
    {
        $this->minBarsBetween = $bars;
    }
    public function setPriceTolerance($tolerance)
    {
        $this->priceTolerance = $tolerance;
    }
    public function setMaxSlPercent($percent)
    {
        $this->maxSlPercent = $percent;
    }
}
