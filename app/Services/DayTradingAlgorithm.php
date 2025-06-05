<?php

namespace App\Services;

class DayTradingAlgorithm
{
    private $config;
    
    public function __construct($config = [])
    {
        $this->config = array_merge([
            'rsi_oversold' => 30,
            'rsi_overbought' => 70,
            'stoch_oversold' => 20,
            'stoch_overbought' => 80,
            'bb_squeeze_threshold' => 0.02,
            'volume_multiplier' => 1.5,
            'adx_trend_threshold' => 25,
            'macd_signal_threshold' => 0.001,
            'ema_separation_threshold' => 0.005,
            'min_confirmation_signals' => 2
        ], $config);
    }

    /**
     * Main entry point detection function
     */
    public function handleOpeningConditions($data, $index)
    {
        // Ensure we have enough historical data
        if ($index < 50) {
            return null;
        }

        $current = $data[$index];
        $previous = $data[$index - 1];
        
        // Get market context
        $marketContext = $this->getMarketContext($data, $index);
        
        // Strategy 1: EMA Crossover with RSI Divergence
        $emaSignal = $this->emaRsiStrategy($data, $index, $marketContext);
        
        // Strategy 2: Bollinger Bands Squeeze Breakout
        $bbSignal = $this->bollingerSqueezeStrategy($data, $index, $marketContext);
        
        // Strategy 3: MACD + Stochastic Confluence
        $macdStochSignal = $this->macdStochasticStrategy($data, $index, $marketContext);
        
        // Strategy 4: Volume Profile Breakout
        $volumeSignal = $this->volumeBreakoutStrategy($data, $index, $marketContext);
        
        // Strategy 5: ADX Trend Following
        $trendSignal = $this->adxTrendStrategy($data, $index, $marketContext);
        
        // Combine signals with weighted scoring
        $signals = [
            'ema_rsi' => $emaSignal,
            'bb_squeeze' => $bbSignal,
            'macd_stoch' => $macdStochSignal,
            'volume' => $volumeSignal,
            'trend' => $trendSignal
        ];
        
        return $this->combineSignals($signals, $current, $marketContext);
    }

    /**
     * Strategy 1: EMA Crossover with RSI Divergence
     */
    private function emaRsiStrategy($data, $index, $marketContext)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];
        
        // EMA 12/26 crossover
        $emaCrossover = $this->getEmaCrossover($current, $previous);
        
        // RSI conditions
        $rsiOversold = $current['rsi6'] < $this->config['rsi_oversold'] && $previous['rsi6'] >= $this->config['rsi_oversold'];
        $rsiOverbought = $current['rsi6'] > $this->config['rsi_overbought'] && $previous['rsi6'] <= $this->config['rsi_overbought'];
        
        // Price above/below key moving averages
        $priceAboveMA25 = $current['close'] > $current['ma25'];
        $priceBelowMA25 = $current['close'] < $current['ma25'];
        
        if ($emaCrossover === 'bullish' && $rsiOversold && $priceAboveMA25) {
            return ['signal' => 'LONG', 'strength' => 0.8, 'reason' => 'EMA bullish crossover + RSI oversold recovery'];
        }
        
        if ($emaCrossover === 'bearish' && $rsiOverbought && $priceBelowMA25) {
            return ['signal' => 'SHORT', 'strength' => 0.8, 'reason' => 'EMA bearish crossover + RSI overbought rejection'];
        }
        
        return null;
    }

    /**
     * Strategy 2: Bollinger Bands Squeeze Breakout
     */
    private function bollingerSqueezeStrategy($data, $index, $marketContext)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];
        
        // Bollinger Bands squeeze detection
        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $prevBbWidth = ($previous['bb_upper'] - $previous['bb_lower']) / $previous['bb_middle'];
        
        $isSqueeze = $bbWidth < $this->config['bb_squeeze_threshold'];
        $wasInSqueeze = $prevBbWidth < $this->config['bb_squeeze_threshold'];
        
        // Volume confirmation
        $volumeBreakout = $current['volume'] > $current['volumeMA5'] * $this->config['volume_multiplier'];
        
        // Breakout detection
        $upperBreakout = $current['close'] > $current['bb_upper'] && $previous['close'] <= $previous['bb_upper'];
        $lowerBreakout = $current['close'] < $current['bb_lower'] && $previous['close'] >= $previous['bb_lower'];
        
        if ($wasInSqueeze && $upperBreakout && $volumeBreakout) {
            return ['signal' => 'LONG', 'strength' => 0.9, 'reason' => 'BB squeeze breakout upward with volume'];
        }
        
        if ($wasInSqueeze && $lowerBreakout && $volumeBreakout) {
            return ['signal' => 'SHORT', 'strength' => 0.9, 'reason' => 'BB squeeze breakout downward with volume'];
        }
        
        return null;
    }

    /**
     * Strategy 3: MACD + Stochastic Confluence
     */
    private function macdStochasticStrategy($data, $index, $marketContext)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];
        
        // MACD signal line crossover
        $macdBullishCross = $current['dif'] > $current['dea'] && $previous['dif'] <= $previous['dea'];
        $macdBearishCross = $current['dif'] < $current['dea'] && $previous['dif'] >= $previous['dea'];
        
        // MACD histogram momentum
        $histogramIncreasing = $current['histogram'] > $previous['histogram'];
        $histogramDecreasing = $current['histogram'] < $previous['histogram'];
        
        // Stochastic conditions
        $stochOversold = $current['stoch_k'] < $this->config['stoch_oversold'];
        $stochOverbought = $current['stoch_k'] > $this->config['stoch_overbought'];
        $stochBullishCross = $current['stoch_k'] > $current['stoch_d'] && $previous['stoch_k'] <= $previous['stoch_d'];
        $stochBearishCross = $current['stoch_k'] < $current['stoch_d'] && $previous['stoch_k'] >= $previous['stoch_d'];
        
        // Long signal
        if ($macdBullishCross && $histogramIncreasing && ($stochOversold || $stochBullishCross)) {
            return ['signal' => 'LONG', 'strength' => 0.85, 'reason' => 'MACD bullish cross + Stochastic oversold/bullish cross'];
        }
        
        // Short signal
        if ($macdBearishCross && $histogramDecreasing && ($stochOverbought || $stochBearishCross)) {
            return ['signal' => 'SHORT', 'strength' => 0.85, 'reason' => 'MACD bearish cross + Stochastic overbought/bearish cross'];
        }
        
        return null;
    }

    /**
     * Strategy 4: Volume Profile Breakout
     */
    private function volumeBreakoutStrategy($data, $index, $marketContext)
    {
        $current = $data[$index];
        
        // Get recent price action
        $recentHigh = $this->getRecentHigh($data, $index, 10);
        $recentLow = $this->getRecentLow($data, $index, 10);
        
        // Volume and price breakout
        $volumeSpike = $current['volume'] > $current['volumeMA10'] * 2;
        $priceBreakoutUp = $current['close'] > $recentHigh;
        $priceBreakoutDown = $current['close'] < $recentLow;
        
        // OBV confirmation
        $obvBullish = $current['obv'] > $current['previousObvHigh'];
        $obvBearish = $current['obv'] < $current['previousObvLow'];
        
        if ($priceBreakoutUp && $volumeSpike && $obvBullish) {
            return ['signal' => 'LONG', 'strength' => 0.75, 'reason' => 'Volume breakout upward with OBV confirmation'];
        }
        
        if ($priceBreakoutDown && $volumeSpike && $obvBearish) {
            return ['signal' => 'SHORT', 'strength' => 0.75, 'reason' => 'Volume breakout downward with OBV confirmation'];
        }
        
        return null;
    }

    /**
     * Strategy 5: ADX Trend Following
     */
    private function adxTrendStrategy($data, $index, $marketContext)
    {
        $current = $data[$index];
        $previous = $data[$index - 1];
        
        // Strong trend detection
        $strongTrend = $current['adx'] > $this->config['adx_trend_threshold'];
        $trendStrengthening = $current['adx'] > $previous['adx'];
        
        // Directional movement
        $bullishDM = $current['di_plus'] > $current['di_minus'] && $previous['di_plus'] <= $previous['di_minus'];
        $bearishDM = $current['di_minus'] > $current['di_plus'] && $previous['di_minus'] <= $previous['di_plus'];
        
        // Parabolic SAR confirmation
        $sarBullish = $current['close'] > $current['sar'];
        $sarBearish = $current['close'] < $current['sar'];
        
        if ($strongTrend && $trendStrengthening && $bullishDM && $sarBullish) {
            return ['signal' => 'LONG', 'strength' => 0.7, 'reason' => 'Strong bullish trend with DM+ crossover'];
        }
        
        if ($strongTrend && $trendStrengthening && $bearishDM && $sarBearish) {
            return ['signal' => 'SHORT', 'strength' => 0.7, 'reason' => 'Strong bearish trend with DM- crossover'];
        }
        
        return null;
    }

    /**
     * Combine all signals with weighted scoring
     */
    private function combineSignals($signals, $current, $marketContext)
    {
        $longScore = 0;
        $shortScore = 0;
        $totalSignals = 0;
        $reasons = [];
        
        foreach ($signals as $strategy => $signal) {
            if ($signal) {
                $totalSignals++;
                $reasons[] = $signal['reason'];
                
                if ($signal['signal'] === 'LONG') {
                    $longScore += $signal['strength'];
                } elseif ($signal['signal'] === 'SHORT') {
                    $shortScore += $signal['strength'];
                }
            }
        }
        
        // Require minimum confirmation signals
        if ($totalSignals < $this->config['min_confirmation_signals']) {
            return null;
        }
        
        // Market context filter
        if (!$this->isMarketConditionFavorable($marketContext)) {
            return null;
        }
        
        // Determine final signal
        if ($longScore > $shortScore && $longScore >= 1.5) {
            return 'LONG';
        } elseif ($shortScore > $longScore && $shortScore >= 1.5) {
            return 'SHORT';
        }
        
        return null;
    }

    /**
     * Get market context for filtering
     */
    private function getMarketContext($data, $index)
    {
        $current = $data[$index];
        
        return [
            'volatility' => $this->calculateVolatility($data, $index, 20),
            'trend_direction' => $this->getTrendDirection($data, $index),
            'volume_profile' => $this->getVolumeProfile($data, $index),
            'time_of_day' => $this->getTimeContext($current['timestamp'])
        ];
    }

    /**
     * Helper functions
     */
    private function getEmaCrossover($current, $previous)
    {
        if ($current['ema12'] > $current['ema26'] && $previous['ema12'] <= $previous['ema26']) {
            return 'bullish';
        } elseif ($current['ema12'] < $current['ema26'] && $previous['ema12'] >= $previous['ema26']) {
            return 'bearish';
        }
        return null;
    }

    private function getRecentHigh($data, $index, $periods)
    {
        $high = 0;
        for ($i = max(0, $index - $periods); $i < $index; $i++) {
            $high = max($high, $data[$i]['high']);
        }
        return $high;
    }

    private function getRecentLow($data, $index, $periods)
    {
        $low = PHP_FLOAT_MAX;
        for ($i = max(0, $index - $periods); $i < $index; $i++) {
            $low = min($low, $data[$i]['low']);
        }
        return $low;
    }

    private function calculateVolatility($data, $index, $periods)
    {
        $prices = [];
        for ($i = max(0, $index - $periods); $i <= $index; $i++) {
            $prices[] = $data[$i]['close'];
        }
        
        if (count($prices) < 2) return 0;
        
        $mean = array_sum($prices) / count($prices);
        $variance = 0;
        
        foreach ($prices as $price) {
            $variance += pow($price - $mean, 2);
        }
        
        return sqrt($variance / count($prices));
    }

    private function getTrendDirection($data, $index)
    {
        $current = $data[$index];
        
        if ($current['ma7'] > $current['ma25'] && $current['ma25'] > $current['ma99']) {
            return 'bullish';
        } elseif ($current['ma7'] < $current['ma25'] && $current['ma25'] < $current['ma99']) {
            return 'bearish';
        }
        
        return 'sideways';
    }

    private function getVolumeProfile($data, $index)
    {
        $current = $data[$index];
        
        if ($current['volume'] > $current['volumeMA10'] * 1.5) {
            return 'high';
        } elseif ($current['volume'] < $current['volumeMA10'] * 0.7) {
            return 'low';
        }
        
        return 'normal';
    }

    private function getTimeContext($timestamp)
    {
        $hour = (int) date('H', strtotime($timestamp));
        
        if ($hour >= 9 && $hour <= 11) {
            return 'morning_session';
        } elseif ($hour >= 13 && $hour <= 16) {
            return 'afternoon_session';
        }
        
        return 'low_volume_period';
    }

    private function isMarketConditionFavorable($context)
    {
        // Avoid trading during low volatility periods
        if ($context['volatility'] < 0.01) {
            return false;
        }
        
        // Avoid trading during low volume periods
        if ($context['volume_profile'] === 'low' && $context['time_of_day'] === 'low_volume_period') {
            return false;
        }
        
        return true;
    }
}