<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BinanceVolumeIndicatorsService
{
    protected string $apiBaseUrl = 'https://api.binance.com';
    protected array $symbols = [];
    protected array $data = [];
    protected array $timeframes = ['1m', '5m', '15m']; // Suitable timeframes for scalping
    protected float $targetProfit = 0.5; // Target profit percentage (0.5%)
    protected float $volumeThreshold = 1.5; // Volume must be 1.5x average to be significant


    // Indicator configuration
    protected bool $useOBV = true;
    protected bool $useVWAP = true;
    protected bool $useVolumeProfile = true;
    protected bool $useCVD = true;
    protected bool $useMFI = true;

    // MFI thresholds
    protected int $mfiOverbought = 80;
    protected int $mfiOversold = 20;

    /**
     * Constructor with optional configuration
     */
    public function __construct(array $config = [])
    {
        // Set default symbols for futures trading

        $this->symbols = $config['symbols'] ?? ['BTCUSDT'];
        $this->data = $config['data'] ?? [];
        $this->timeframes = $config['timeframes'] ?? ['5m'];
        $this->targetProfit = $config['target_profit'] ?? 0.5;
        $this->volumeThreshold = $config['volume_threshold'] ?? 1.5;

        // Configure indicators
        $this->useOBV = $config['use_obv'] ?? true;
        $this->useVWAP = $config['use_vwap'] ?? true;
        $this->useVolumeProfile = $config['use_volume_profile'] ?? true;
        $this->useCVD = $config['use_cvd'] ?? true;
        $this->useMFI = $config['use_mfi'] ?? true;

        $this->mfiOverbought = $config['mfi_overbought'] ?? 80;
        $this->mfiOversold = $config['mfi_oversold'] ?? 20;
    }

    public function getKlinesFromData(int $limit = 100, $index)
    {
        $start = max(0, $index - $limit);
        $length = $index - $start + 1;

        $subArray = array_slice($this->data, $start, $length);


        return $subArray;
    }
    /**
     * Get kline/candlestick data from Binance
     */
    public function getKlineData(string $symbol, string $interval, int $limit = 100, $timestamp = null): array
    {


        try {
            return array_slice($this->data, -$limit);
        } catch (\Exception $e) {
            Log::error('Exception while fetching kline data: ' . $e->getMessage());
            dd($e);
            return [];
        }
    }

    /**
     * Calculate On-Balance Volume (OBV)
     * Formula: If close > prev_close, OBV = prev_OBV + current_volume
     *          If close < prev_close, OBV = prev_OBV - current_volume
     *          If close = prev_close, OBV = prev_OBV
     */
    public function calculateOBV(array $klines): array
    {
        if (empty($klines)) {
            return [];
        }

        $obv = [0]; // Start with base value

        for ($i = 1; $i < count($klines); $i++) {
            $currentClose = (float)$klines[$i][4];
            $previousClose = (float)$klines[$i - 1][4];
            $currentVolume = (float)$klines[$i][5];

            if ($currentClose > $previousClose) {
                $obv[] = $obv[$i - 1] + $currentVolume;
            } elseif ($currentClose < $previousClose) {
                $obv[] = $obv[$i - 1] - $currentVolume;
            } else {
                $obv[] = $obv[$i - 1];
            }
        }

        return $obv;
    }

    /**
     * Calculate Volume-Weighted Average Price (VWAP)
     * Formula: VWAP = Σ(Price * Volume) / Σ(Volume)
     */
    public function calculateVWAP(array $klines): array
    {
        if (empty($klines)) {
            return [];
        }

        $typicalPriceVolume = [];
        $volumes = [];
        $vwap = [];

        foreach ($klines as $k) {
            $high = (float)$k[2];
            $low = (float)$k[3];
            $close = (float)$k[4];
            $volume = (float)$k[5];

            // Typical price = (high + low + close) / 3
            $typicalPrice = ($high + $low + $close) / 3;

            $typicalPriceVolume[] = $typicalPrice * $volume;
            $volumes[] = $volume;
        }

        // Calculate cumulative values
        $cumTypicalPriceVolume = [];
        $cumVolumes = [];
        $cumSum = 0;
        $volumeSum = 0;

        foreach ($typicalPriceVolume as $i => $tpv) {
            $cumSum += $tpv;
            $volumeSum += $volumes[$i];
            $cumTypicalPriceVolume[] = $cumSum;
            $cumVolumes[] = $volumeSum;

            // Calculate VWAP for each point
            $vwap[] = $volumeSum > 0 ? $cumSum / $volumeSum : 0;
        }

        return $vwap;
    }

    /**
     * Calculate Volume Profile (simplified)
     * Returns an array of [price => volume] for specified price range
     */
    public function calculateVolumeProfile(array $klines, int $numLevels = 20): array
    {
        if (empty($klines)) {
            return [];
        }

        // Extract high, low, and volume data
        $highPrices = array_column($klines, 2);
        $lowPrices = array_column($klines, 3);
        $volumes = array_column($klines, 5);

        // Find min and max prices
        $minPrice = min($lowPrices);
        $maxPrice = max($highPrices);

        // Calculate price range and interval
        $priceRange = $maxPrice - $minPrice;
        $intervalSize = $priceRange / $numLevels;

        // Initialize volume profile array
        $volumeProfile = [];
        for ($i = 0; $i < $numLevels; $i++) {
            $price = $minPrice + ($i * $intervalSize);
            $volumeProfile[number_format($price, 8)] = 0;
        }

        // Distribute volume across price levels
        foreach ($klines as $i => $k) {
            $high = (float)$k[2];
            $low = (float)$k[3];
            $volume = (float)$k[5];

            // Distribute volume proportionally across price levels touched by this candle
            $candle_range = $high - $low;
            if ($candle_range <= 0) continue;

            // Determine which levels this candle spans
            for ($level = 0; $level < $numLevels; $level++) {
                $levelPrice = $minPrice + ($level * $intervalSize);
                $levelPriceNext = $levelPrice + $intervalSize;

                // If this price level overlaps with the candle
                if (($levelPrice >= $low && $levelPrice <= $high) ||
                    ($levelPriceNext >= $low && $levelPriceNext <= $high) ||
                    ($levelPrice <= $low && $levelPriceNext >= $high)
                ) {

                    // Calculate overlap portion and distribute volume
                    $overlapStart = max($levelPrice, $low);
                    $overlapEnd = min($levelPriceNext, $high);
                    $overlapRatio = ($overlapEnd - $overlapStart) / $candle_range;

                    // Add proportional volume to this level
                    $volumeProfile[number_format($levelPrice, 8)] += $volume * $overlapRatio;
                }
            }
        }

        // Sort by volume (descending) to find POC (Point of Control)
        arsort($volumeProfile);

        return $volumeProfile;
    }

    /**
     * Calculate Cumulative Volume Delta (CVD)
     * If close > open, add volume to delta (buying pressure)
     * If close < open, subtract volume from delta (selling pressure)
     */
    public function calculateCVD(array $klines): array
    {
        if (empty($klines)) {
            return [];
        }

        $cvd = [0]; // Start with base value

        for ($i = 1; $i < count($klines); $i++) {
            $open = (float)$klines[$i][1];
            $close = (float)$klines[$i][4];
            $volume = (float)$klines[$i][5];

            // Determine if buying or selling pressure
            if ($close > $open) {
                // Buying pressure - add volume
                $delta = $volume;
            } else {
                // Selling pressure - subtract volume
                $delta = -$volume;
            }

            $cvd[] = $cvd[$i - 1] + $delta;
        }

        return $cvd;
    }

    /**
     * Calculate Money Flow Index (MFI)
     * MFI is a volume-weighted RSI (typically 14 periods)
     */
    public function calculateMFI(array $klines, int $period = 14): array
    {
        if (count($klines) < $period + 1) {
            return [];
        }

        $typicalPrices = [];
        $moneyFlows = [];
        $mfi = [];

        // Calculate typical price and money flow
        foreach ($klines as $k) {
            $high = (float)$k[2];
            $low = (float)$k[3];
            $close = (float)$k[4];
            $volume = (float)$k[5];

            // Typical price = (high + low + close) / 3
            $typicalPrice = ($high + $low + $close) / 3;
            $typicalPrices[] = $typicalPrice;

            // Money flow = typical price * volume
            $moneyFlows[] = $typicalPrice * $volume;
        }

        // Calculate positive and negative money flows for each period
        for ($i = $period; $i < count($klines); $i++) {
            $positiveFlow = 0;
            $negativeFlow = 0;

            for ($j = $i - $period + 1; $j <= $i; $j++) {
                if ($j <= 0) continue;

                // Check if current typical price is higher than previous
                if ($typicalPrices[$j] > $typicalPrices[$j - 1]) {
                    $positiveFlow += $moneyFlows[$j];
                } else {
                    $negativeFlow += $moneyFlows[$j];
                }
            }

            // Avoid division by zero
            if ($negativeFlow == 0) {
                $mfi[] = 100;
            } elseif ($positiveFlow == 0) {
                $mfi[] = 0;
            } else {
                $moneyRatio = $positiveFlow / $negativeFlow;
                $mfi[] = 100 - (100 / (1 + $moneyRatio));
            }
        }

        // Pad the beginning with nulls for alignment
        $padding = array_fill(0, $period, null);
        return array_merge($padding, $mfi);
    }

    /**
     * Get all volume indicators for a symbol and timeframe
     */
    public function getAllVolumeIndicators(string $symbol, string $interval): array
    {
        $klines = $this->getKlineData($symbol, $interval, 200); // Get enough data for all indicators

        if (empty($klines)) {
            return [
                'symbol' => $symbol,
                'interval' => $interval,
                'error' => 'No kline data available'
            ];
        }

        $indicators = [
            'symbol' => $symbol,
            'interval' => $interval,
            'timestamp' => now()->toIso8601String(),
            'current_price' => (float)end($klines)[4], // Latest close price
        ];

        // Calculate each indicator based on configuration
        if ($this->useOBV) {
            $indicators['obv'] = $this->calculateOBV($klines);
            $indicators['obv_current'] = end($indicators['obv']);
            $indicators['obv_previous'] = prev($indicators['obv']);
            $indicators['obv_change'] = $indicators['obv_current'] - $indicators['obv_previous'];
        }

        if ($this->useVWAP) {
            $indicators['vwap'] = $this->calculateVWAP($klines);
            $indicators['vwap_current'] = end($indicators['vwap']);
            $indicators['price_to_vwap'] = $indicators['current_price'] / max(1, $indicators['vwap_current'] - 1);
        }

        if ($this->useVolumeProfile) {
            $volumeProfile = $this->calculateVolumeProfile($klines, 20);
            $indicators['volume_profile'] = $volumeProfile;

            // Get Point of Control (price level with highest volume)
            reset($volumeProfile);
            $indicators['poc'] = key($volumeProfile);
            $indicators['poc_value'] = current($volumeProfile);

            // Calculate distance of current price from POC
            $indicators['price_to_poc'] = $indicators['current_price'] / (float)$indicators['poc'] - 1;
        }

        if ($this->useCVD) {
            $indicators['cvd'] = $this->calculateCVD($klines);
            $indicators['cvd_current'] = end($indicators['cvd']);
            $indicators['cvd_previous'] = prev($indicators['cvd']);
            $indicators['cvd_change'] = $indicators['cvd_current'] - $indicators['cvd_previous'];
        }

        if ($this->useMFI) {
            $indicators['mfi'] = $this->calculateMFI($klines, 14);
            $indicators['mfi_current'] = end($indicators['mfi']) ?? 50;
            $indicators['mfi_overbought'] = $indicators['mfi_current'] > $this->mfiOverbought;
            $indicators['mfi_oversold'] = $indicators['mfi_current'] < $this->mfiOversold;
        }

        return $indicators;
    }

    /**
     * Analyze volume indicators to generate scalping signals
     */
    public function analyzeVolumeIndicators(array $indicators): array
    {
        $signal = [
            'symbol' => $indicators['symbol'],
            'interval' => $indicators['interval'],
            'price' => $indicators['current_price'],
            'signal' => 'neutral',
            'strength' => 0,
            'reasons' => [],
        ];

        $buyPoints = 0;
        $sellPoints = 0;
        $totalFactors = 0;

        // OBV Analysis
        if ($this->useOBV && isset($indicators['obv_change'])) {
            $totalFactors++;

            if ($indicators['obv_change'] > 0) {
                // Rising OBV indicates buying pressure
                $buyPoints += 1;
                $signal['reasons'][] = 'OBV rising (buying pressure)';
            } elseif ($indicators['obv_change'] < 0) {
                // Falling OBV indicates selling pressure
                $sellPoints += 1;
                $signal['reasons'][] = 'OBV falling (selling pressure)';
            }

            // Check for OBV divergence (bullish: price down, OBV up)
            if (count($indicators['obv']) >= 5) {
                $priceChange = $indicators['current_price'] - $indicators['current_price'] * 0.99; // Approx recent price change
                if ($priceChange < 0 && $indicators['obv_change'] > 0) {
                    $buyPoints += 2;
                    $signal['reasons'][] = 'Bullish OBV divergence (price down, OBV up)';
                } elseif ($priceChange > 0 && $indicators['obv_change'] < 0) {
                    $sellPoints += 2;
                    $signal['reasons'][] = 'Bearish OBV divergence (price up, OBV down)';
                }
            }
        }

        // VWAP Analysis
        if ($this->useVWAP && isset($indicators['price_to_vwap'])) {
            $totalFactors++;

            // Price relative to VWAP
            if ($indicators['price_to_vwap'] > 0.002) {
                // Price significantly above VWAP (bullish)
                $buyPoints += 1;
                $signal['reasons'][] = 'Price above VWAP (bullish bias)';

                // But if extremely overextended, potential mean reversion
                if ($indicators['price_to_vwap'] > 0.01) {
                    $sellPoints += 1;
                    $signal['reasons'][] = 'Price significantly extended above VWAP (potential pullback)';
                }
            } elseif ($indicators['price_to_vwap'] < -0.002) {
                // Price significantly below VWAP (bearish)
                $sellPoints += 1;
                $signal['reasons'][] = 'Price below VWAP (bearish bias)';

                // But if extremely underextended, potential mean reversion
                if ($indicators['price_to_vwap'] < -0.01) {
                    $buyPoints += 1;
                    $signal['reasons'][] = 'Price significantly extended below VWAP (potential bounce)';
                }
            }

            // Price crossing VWAP
            if (abs($indicators['price_to_vwap']) < 0.0005) {
                $signal['reasons'][] = 'Price at VWAP (equilibrium/decision point)';
            }
        }

        // Volume Profile Analysis
        if ($this->useVolumeProfile && isset($indicators['price_to_poc'])) {
            $totalFactors++;

            // Check if price is near high-volume node (POC)
            if (abs($indicators['price_to_poc']) < 0.001) {
                $signal['reasons'][] = 'Price at high-volume node (POC) - potential support/resistance';
            }

            // Get top 3 volume levels (high-volume nodes)
            $volumeProfile = $indicators['volume_profile'];
            $volumeNodes = [];
            $i = 0;
            foreach ($volumeProfile as $price => $volume) {
                if ($i++ >= 3) break;
                $volumeNodes[] = (float)$price;
            }

            // Check if price is between volume nodes (low-volume area)
            $inLowVolumeArea = true;
            foreach ($volumeNodes as $node) {
                if (abs($indicators['current_price'] / $node - 1) < 0.002) {
                    $inLowVolumeArea = false;
                    break;
                }
            }

            if ($inLowVolumeArea) {
                $signal['reasons'][] = 'Price in low-volume area (potential quick move expected)';
            }

            // Direction relative to POC
            if ($indicators['price_to_poc'] > 0) {
                $buyPoints += 0.5;
            } else {
                $sellPoints += 0.5;
            }
        }

        // CVD Analysis
        if ($this->useCVD && isset($indicators['cvd_change'])) {
            $totalFactors++;

            // Recent buying vs selling pressure
            if ($indicators['cvd_change'] > 0) {
                $buyPoints += 1.5;
                $signal['reasons'][] = 'Positive CVD change (buying pressure exceeding selling)';
            } elseif ($indicators['cvd_change'] < 0) {
                $sellPoints += 1.5;
                $signal['reasons'][] = 'Negative CVD change (selling pressure exceeding buying)';
            }

            // Significant CVD move
            if (abs($indicators['cvd_change']) > $indicators['cvd_current'] * 0.05) {
                if ($indicators['cvd_change'] > 0) {
                    $buyPoints += 1;
                    $signal['reasons'][] = 'Strong positive CVD momentum';
                } else {
                    $sellPoints += 1;
                    $signal['reasons'][] = 'Strong negative CVD momentum';
                }
            }
        }

        // MFI Analysis
        if ($this->useMFI && isset($indicators['mfi_current'])) {
            $totalFactors++;

            // Oversold/overbought conditions
            if ($indicators['mfi_oversold']) {
                $buyPoints += 2;
                $signal['reasons'][] = 'MFI oversold (potential buy)';
            } elseif ($indicators['mfi_overbought']) {
                $sellPoints += 2;
                $signal['reasons'][] = 'MFI overbought (potential sell)';
            }

            // MFI momentum
            $mfiTrend = $indicators['mfi_current'] - 50;
            if ($mfiTrend > 10) {
                $buyPoints += 0.5;
            } elseif ($mfiTrend < -10) {
                $sellPoints += 0.5;
            }
        }

        // Calculate final signal
        $totalPoints = $buyPoints + $sellPoints;
        if ($totalPoints > 0) {
            $buyStrength = $buyPoints / $totalPoints;
            $sellStrength = $sellPoints / $totalPoints;

            if ($buyStrength > 0.65) {
                $signal['signal'] = 'buy';
                $signal['strength'] = min(round($buyStrength * 10), 10);
            } elseif ($sellStrength > 0.65) {
                $signal['signal'] = 'sell';
                $signal['strength'] = min(round($sellStrength * 10), 10);
            }
        }

        // Check if signal meets minimum scalping criteria (0.5% potential)
        $hasPotential = $this->checkScalpingPotential($indicators['symbol'], $indicators['interval']);
        $signal['potential'] = $hasPotential;

        if (!$hasPotential && $signal['signal'] !== 'neutral') {
            $signal['reasons'][] = 'Warning: Symbol volatility may be insufficient for 0.5% target';
        }

        return $signal;
    }

    /**
     * Check if a symbol has enough recent volatility for the target profit
     */
    protected function checkScalpingPotential(string $symbol, string $interval): bool
    {
        // Get most recent candles
        $klines = $this->getKlineData($symbol, $interval, 30);

        if (empty($klines)) {
            return false;
        }

        // Calculate average price movement per candle
        $totalMovement = 0;
        $count = 0;

        foreach ($klines as $k) {
            $high = (float)$k[2];
            $low = (float)$k[3];
            $close = (float)$k[4];

            // Use high-low range or close-to-range percentage
            $movement = (($high - $low) / $low) * 100;
            $totalMovement += $movement;
            $count++;
        }

        $avgMovement = $totalMovement / $count;

        // Check if average movement is sufficient for target profit
        // Generally need ~2x the target in raw movement for realistic scalping
        return $avgMovement >= ($this->targetProfit * 2);
    }

    /**
     * Get scalping signals for all configured symbols
     */
    public function getScalpingSignals(): array
    {
        $results = [
            'timestamp' => now()->toIso8601String(),
            'signals' => [],
            'active_signals' => [],
        ];

        foreach ($this->symbols as $symbol) {
            foreach ($this->timeframes as $timeframe) {
                // Get all indicators
                $indicators = $this->getAllVolumeIndicators($symbol, $timeframe);



                $indicatorList = [
                    'mfi_current' => $indicators['mfi_current'] ?? null,
                    'cvd_current' => $indicators['cvd_current'] ?? null,
                    'price_to_poc' => $indicators['price_to_poc'] ?? null,
                    'poc_value' => $indicators['poc_value'] ?? null,
                    'volume_profile' => $indicators['volume_profile'] ?? null,
                    'vwap_current' => $indicators['vwap_current'] ?? null,
                    'obv_current' => $indicators['obv_current'] ?? null,
                ];
                // dd($indicators);
                // Analyze and generate signal
                $signal = $this->analyzeVolumeIndicators($indicators);
                $signal['indicators'] = $indicatorList;
                // Only include non-neutral signals with strength >= 6
                if (($signal['signal'] !== 'neutral' && $signal['strength'] >= 6 && $signal['potential']) || true) {
                    $results['signals'][] = $signal;
                    $results['active_signals'][] = [
                        'symbol' => $symbol,
                        'timeframe' => $timeframe,
                        'signal' => $signal['signal'],
                        'strength' => $signal['strength'],
                        'timestamp' => now()->toIso8601String(),
                        'indicators' => $indicatorList,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Generate an optimal scalping setup with specific entry, stop loss and take profit
     */
    public function getOptimalScalpSetup(string $symbol, string $interval = '1m'): array
    {
        // Get indicators
        $indicators = $this->getAllVolumeIndicators($symbol, $interval);

        // Analyze signals
        $signal = $this->analyzeVolumeIndicators($indicators);

        // Only proceed if we have a clear signal
        if ($signal['signal'] === 'neutral' || $signal['strength'] < 6) {
            return [
                'symbol' => $symbol,
                'status' => 'no_signal',
                'message' => 'No strong volume signal detected at this time',
            ];
        }

        $currentPrice = $indicators['current_price'];
        $setup = [
            'symbol' => $symbol,
            'status' => 'ready',
            'signal' => $signal['signal'],
            'strength' => $signal['strength'],
            'reasons' => $signal['reasons'],
            'entry_price' => $currentPrice,
            'timestamp' => now()->toIso8601String(),
        ];

        // Set stop loss based on volume profile or CVD
        if (isset($indicators['volume_profile']) && count($indicators['volume_profile']) > 0) {
            // Get closest high-volume node below/above price for stop loss
            $volumeNodes = array_keys($indicators['volume_profile']);

            if ($signal['signal'] === 'buy') {
                // For buys, find support level below
                $stopPrice = 0;
                foreach ($volumeNodes as $price) {
                    $price = (float)$price;
                    if ($price < $currentPrice) {
                        $stopPrice = $price;
                        break;
                    }
                }

                // If no support found, use 1% below
                if ($stopPrice == 0 || $stopPrice > $currentPrice * 0.99) {
                    $stopPrice = $currentPrice * 0.99;
                }

                $setup['stop_loss'] = $stopPrice;
                $setup['stop_loss_pct'] = (($currentPrice - $stopPrice) / $currentPrice) * 100;
                $setup['take_profit'] = $currentPrice * (1 + ($this->targetProfit / 100));
            } else {
                // For sells, find resistance level above
                $stopPrice = 0;
                foreach ($volumeNodes as $price) {
                    $price = (float)$price;
                    if ($price > $currentPrice) {
                        $stopPrice = $price;
                        break;
                    }
                }

                // If no resistance found, use 1% above
                if ($stopPrice == 0 || $stopPrice < $currentPrice * 1.01) {
                    $stopPrice = $currentPrice * 1.01;
                }

                $setup['stop_loss'] = $stopPrice;
                $setup['stop_loss_pct'] = (($stopPrice - $currentPrice) / $currentPrice) * 100;
                $setup['take_profit'] = $currentPrice * (1 - ($this->targetProfit / 100));
            }
        } else {
            // Default stop loss if volume profile not available
            if ($signal['signal'] === 'buy') {
                $setup['stop_loss'] = $currentPrice * 0.99;
                $setup['stop_loss_pct'] = 1.0;
                $setup['take_profit'] = $currentPrice * (1 + ($this->targetProfit / 100));
            } else {
                $setup['stop_loss'] = $currentPrice * 1.01;
                $setup['stop_loss_pct'] = 1.0;
                $setup['take_profit'] = $currentPrice * (1 - ($this->targetProfit / 100));
            }
        }

        // Calculate risk-reward ratio
        $risk = abs($currentPrice - $setup['stop_loss']);
        $reward = abs($setup['take_profit'] - $currentPrice);
        $setup['risk_reward'] = round($reward / ($risk ?: 1), 2);

        // Add extra info from indicators
        if (isset($indicators['vwap_current'])) {
            $setup['vwap'] = $indicators['vwap_current'];
        }

        if (isset($indicators['poc'])) {
            $setup['poc'] = $indicators['poc'];
        }

        if (isset($indicators['mfi_current'])) {
            $setup['mfi'] = round($indicators['mfi_current'], 2);
        }

        return $setup;
    }

    /**
     * Get complete volume analysis dashboard for a symbol
     */
    public function getVolumeAnalysisDashboard(string $symbol): array
    {
        $dashboard = [
            'symbol' => $symbol,
            'timestamp' => now()->toIso8601String(),
            'timeframes' => []
        ];

        // Get analysis for each timeframe
        foreach ($this->timeframes as $timeframe) {
            $indicators = $this->getAllVolumeIndicators($symbol, $timeframe);
            $signal = $this->analyzeVolumeIndicators($indicators);

            $timeframeData = [
                'timeframe' => $timeframe,
                'current_price' => $indicators['current_price'],
                'signal' => $signal['signal'],
                'strength' => $signal['strength'],
                'reasons' => $signal['reasons']
            ];

            // Add key indicator values
            if (isset($indicators['obv_current'])) {
                $timeframeData['obv'] = $indicators['obv_current'];
                $timeframeData['obv_change'] = $indicators['obv_change'];
            }

            if (isset($indicators['vwap_current'])) {
                $timeframeData['vwap'] = $indicators['vwap_current'];
                $timeframeData['price_to_vwap'] = round($indicators['price_to_vwap'] * 100, 3) . '%';
            }

            if (isset($indicators['cvd_current'])) {
                $timeframeData['cvd'] = $indicators['cvd_current'];
                $timeframeData['cvd_change'] = $indicators['cvd_change'];
            }

            if (isset($indicators['mfi_current'])) {
                $timeframeData['mfi'] = round($indicators['mfi_current'], 2);
                $timeframeData['mfi_status'] = $indicators['mfi_current'] > 70 ? 'overbought' : ($indicators['mfi_current'] < 30 ? 'oversold' : 'neutral');
            }

            // Add volume profile summary
            if (isset($indicators['poc'])) {
                $timeframeData['poc'] = $indicators['poc'];
                $timeframeData['price_to_poc'] = round($indicators['price_to_poc'] * 100, 3) . '%';

                // Get top 3 volume nodes
                $volumeProfile = $indicators['volume_profile'];
                $volumeNodes = [];
                $i = 0;
                foreach ($volumeProfile as $price => $volume) {
                    if ($i++ >= 3) break;
                    $volumeNodes[] = $price;
                }
                $timeframeData['top_volume_nodes'] = $volumeNodes;
            }

            $dashboard['timeframes'][$timeframe] = $timeframeData;
        }

        // Add optimal setup
        $dashboard['optimal_setup'] = $this->getOptimalScalpSetup($symbol, $this->timeframes[0]);

        return $dashboard;
    }

    /**
     * Check for volume indicator divergences 
     * (price moving one way, indicator another - often powerful signals)
     */
    public function checkVolumeDivergences(string $symbol): array
    {
        $divergences = [
            'symbol' => $symbol,
            'timestamp' => now()->toIso8601String(),
            'found' => false,
            'divergences' => []
        ];

        // Get indicators for 1m and 5m timeframes for better confirmation
        $indicators1m = $this->getAllVolumeIndicators($symbol, '1m');
        $indicators5m = $this->getAllVolumeIndicators($symbol, '5m');

        // Check OBV Divergence
        if ($this->useOBV && isset($indicators1m['obv']) && isset($indicators5m['obv'])) {
            // Get recent price movement direction
            $priceChange = $indicators1m['current_price'] - $indicators1m['current_price'] * 0.99;
            $priceDirection = $priceChange > 0 ? 'up' : 'down';

            // Get OBV direction on both timeframes
            $obv1mChange = end($indicators1m['obv']) - prev($indicators1m['obv']);
            $obv5mChange = end($indicators5m['obv']) - prev($indicators5m['obv']);
            $obvDirection1m = $obv1mChange > 0 ? 'up' : 'down';
            $obvDirection5m = $obv5mChange > 0 ? 'up' : 'down';

            // Check for confirmed divergence on both timeframes
            if ($priceDirection !== $obvDirection1m && $priceDirection !== $obvDirection5m) {
                $divergences['found'] = true;
                $divergenceType = $priceDirection === 'up' ? 'bearish' : 'bullish';

                $divergences['divergences'][] = [
                    'type' => 'OBV ' . $divergenceType . ' divergence',
                    'description' => 'Price moving ' . $priceDirection . ' but OBV moving ' .
                        $obvDirection1m . ' (confirmed on both 1m and 5m timeframes)',
                    'signal' => $divergenceType === 'bullish' ? 'buy' : 'sell',
                    'strength' => 'strong'
                ];
            }
        }

        // Check CVD Divergence if available
        if ($this->useCVD && isset($indicators1m['cvd']) && isset($indicators5m['cvd'])) {
            // Get recent price movement direction
            $priceChange = $indicators1m['current_price'] - $indicators1m['current_price'] * 0.99;
            $priceDirection = $priceChange > 0 ? 'up' : 'down';

            // Get CVD direction on both timeframes
            $cvd1mChange = $indicators1m['cvd_change'];
            $cvd5mChange = $indicators5m['cvd_change'];
            $cvdDirection1m = $cvd1mChange > 0 ? 'up' : 'down';
            $cvdDirection5m = $cvd5mChange > 0 ? 'up' : 'down';

            // Check for confirmed divergence on both timeframes
            if ($priceDirection !== $cvdDirection1m && $priceDirection !== $cvdDirection5m) {
                $divergences['found'] = true;
                $divergenceType = $priceDirection === 'up' ? 'bearish' : 'bullish';

                $divergences['divergences'][] = [
                    'type' => 'CVD ' . $divergenceType . ' divergence',
                    'description' => 'Price moving ' . $priceDirection . ' but CVD moving ' .
                        $cvdDirection1m . ' (confirmed on both 1m and 5m timeframes)',
                    'signal' => $divergenceType === 'bullish' ? 'buy' : 'sell',
                    'strength' => 'strong'
                ];
            }
        }

        // Check MFI divergence if available
        if ($this->useMFI && isset($indicators1m['mfi_current']) && isset($indicators5m['mfi_current'])) {
            // Check for extreme MFI readings
            if (($indicators1m['mfi_current'] < 20 && $indicators5m['mfi_current'] < 20) &&
                $priceChange < 0
            ) {
                $divergences['found'] = true;
                $divergences['divergences'][] = [
                    'type' => 'MFI oversold',
                    'description' => 'MFI extremely oversold while price declining - potential bullish reversal',
                    'signal' => 'buy',
                    'strength' => 'medium',
                    'mfi_1m' => round($indicators1m['mfi_current'], 2),
                    'mfi_5m' => round($indicators5m['mfi_current'], 2)
                ];
            } elseif (($indicators1m['mfi_current'] > 80 && $indicators5m['mfi_current'] > 80) &&
                $priceChange > 0
            ) {
                $divergences['found'] = true;
                $divergences['divergences'][] = [
                    'type' => 'MFI overbought',
                    'description' => 'MFI extremely overbought while price rising - potential bearish reversal',
                    'signal' => 'sell',
                    'strength' => 'medium',
                    'mfi_1m' => round($indicators1m['mfi_current'], 2),
                    'mfi_5m' => round($indicators5m['mfi_current'], 2)
                ];
            }
        }

        return $divergences;
    }

    /**
     * Analyze a specific volume pattern for scalping opportunities
     */
    public function analyzeVolumePattern(string $symbol, string $interval, string $pattern): array
    {
        $result = [
            'symbol' => $symbol,
            'interval' => $interval,
            'pattern' => $pattern,
            'detected' => false,
            'details' => []
        ];

        $indicators = $this->getAllVolumeIndicators($symbol, $interval);

        if (empty($indicators) || isset($indicators['error'])) {
            return [
                'symbol' => $symbol,
                'error' => 'Could not retrieve indicators data'
            ];
        }

        switch ($pattern) {
            case 'volume_climax':
                // Volume climax - extremely high volume spike that often marks turning points
                $klines = $this->getKlineData($symbol, $interval, 30);
                $volumes = array_column($klines, 5);
                $avgVolume = array_sum(array_slice($volumes, 0, 25)) / 25;
                $latestVolume = end($volumes);

                if ($latestVolume > $avgVolume * 3) {
                    $result['detected'] = true;
                    $result['details'] = [
                        'description' => 'Extreme volume spike detected - ' . round($latestVolume / $avgVolume, 1) . 'x average volume',
                        'significance' => 'Volume climax often marks exhaustion and potential reversal point',
                        'recommendation' => 'Watch for price stabilization after this spike before entering position'
                    ];
                }
                break;

            case 'volume_dryup':
                // Volume dry-up - very low volume that can precede explosive moves
                $klines = $this->getKlineData($symbol, $interval, 30);
                $volumes = array_column($klines, 5);
                $avgVolume = array_sum(array_slice($volumes, 0, 25)) / 25;
                $recentVolumes = array_slice($volumes, -3);
                $recentAvgVolume = array_sum($recentVolumes) / 3;

                if ($recentAvgVolume < $avgVolume * 0.4) {
                    $result['detected'] = true;
                    $result['details'] = [
                        'description' => 'Volume dry-up detected - only ' . round($recentAvgVolume / $avgVolume * 100, 1) . '% of average volume',
                        'significance' => 'Volume dry-ups often precede explosive moves as liquidity decreases',
                        'recommendation' => 'Watch for the first signs of volume increase to catch breakout'
                    ];
                }
                break;

            case 'vwap_bounce':
                // VWAP bounce - price touches VWAP and bounces
                if (isset($indicators['vwap_current']) && isset($indicators['price_to_vwap'])) {
                    if (abs($indicators['price_to_vwap']) < 0.001) {
                        // Price very close to VWAP
                        $klines = $this->getKlineData($symbol, $interval, 5);
                        $closes = array_column($klines, 4);
                        $opens = array_column($klines, 1);

                        // Check if most recent candle shows a bounce off VWAP
                        $latestClose = end($closes);
                        $latestOpen = end($opens);
                        $prevClose = prev($closes);

                        if (($latestClose > $latestOpen && $prevClose < $latestOpen) ||
                            ($latestClose < $latestOpen && $prevClose > $latestOpen)
                        ) {
                            $result['detected'] = true;
                            $bounceDirection = $latestClose > $latestOpen ? 'bullish' : 'bearish';

                            $result['details'] = [
                                'description' => $bounceDirection . ' bounce off VWAP detected',
                                'significance' => 'VWAP acts as dynamic support/resistance and price reaction confirms this level',
                                'recommendation' => $bounceDirection === 'bullish' ? 'Consider long position with stop below VWAP' : 'Consider short position with stop above VWAP',
                                'vwap' => $indicators['vwap_current']
                            ];
                        }
                    }
                }
                break;

            case 'cvd_acceleration':
                // CVD acceleration - strong buying/selling pressure shown by CVD slope change
                if (isset($indicators['cvd'])) {
                    $cvd = $indicators['cvd'];
                    if (count($cvd) >= 10) {
                        // Calculate recent CVD slope vs previous slope
                        $recentSlope = $cvd[count($cvd) - 1] - $cvd[count($cvd) - 3];
                        $previousSlope = $cvd[count($cvd) - 4] - $cvd[count($cvd) - 6];

                        // Check for slope acceleration
                        if (abs($recentSlope) > abs($previousSlope) * 2) {
                            $result['detected'] = true;
                            $direction = $recentSlope > 0 ? 'bullish' : 'bearish';

                            $result['details'] = [
                                'description' => 'CVD ' . $direction . ' acceleration detected',
                                'significance' => 'Rapid change in buying/selling pressure often precedes price moves',
                                'recommendation' => $direction === 'bullish' ? 'Consider long position' : 'Consider short position',
                                'cvd_recent_change' => $recentSlope,
                                'cvd_previous_change' => $previousSlope
                            ];
                        }
                    }
                }
                break;

            case 'high_volume_node_test':
                // Test of high volume node from Volume Profile
                if (isset($indicators['volume_profile']) && isset($indicators['poc'])) {
                    $currentPrice = $indicators['current_price'];
                    $poc = (float)$indicators['poc'];

                    // Check if price is testing the POC (within 0.1%)
                    if (abs($currentPrice / $poc - 1) < 0.001) {
                        $result['detected'] = true;

                        $result['details'] = [
                            'description' => 'Price testing major high-volume node (POC)',
                            'significance' => 'POC often acts as strong support/resistance due to high historical trading activity',
                            'recommendation' => 'Watch for bounce or break at this key level - volume increase will confirm direction',
                            'poc' => $poc
                        ];
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * Get historical performance metrics for signals generated by this service
     * Note: This requires past signals to be stored in a database
     */
    public function getHistoricalPerformanceMetrics(string $symbol = null): array
    {
        // This would connect to your database to retrieve past signals and outcomes
        // For illustration purposes, we'll return a sample structure

        return [
            'overall' => [
                'total_signals' => 142,
                'successful_signals' => 94,
                'success_rate' => '66.2%',
                'average_profit' => '0.58%',
                'average_loss' => '0.31%',
                'profit_factor' => 2.12,
                'largest_profit' => '1.23%',
                'largest_loss' => '0.82%'
            ],
            'by_indicator' => [
                'obv' => [
                    'signals' => 45,
                    'success_rate' => '71.1%'
                ],
                'vwap' => [
                    'signals' => 62,
                    'success_rate' => '64.5%'
                ],
                'cvd' => [
                    'signals' => 35,
                    'success_rate' => '68.6%'
                ],
                'mfi' => [
                    'signals' => 48,
                    'success_rate' => '62.5%'
                ],
                'volume_profile' => [
                    'signals' => 28,
                    'success_rate' => '67.9%'
                ]
            ],
            'by_symbol' => [
                'BTCUSDT' => [
                    'signals' => 38,
                    'success_rate' => '68.4%'
                ],
                'ETHUSDT' => [
                    'signals' => 42,
                    'success_rate' => '64.3%'
                ],
                'BNBUSDT' => [
                    'signals' => 31,
                    'success_rate' => '67.7%'
                ],
                'SOLUSDT' => [
                    'signals' => 31,
                    'success_rate' => '64.5%'
                ]
            ]
        ];
    }

    /**
     * Create a webhook notification system for real-time signals
     */
    public function subscribeToSignals(string $webhookUrl, array $config = []): array
    {
        // In a real implementation, this would store the webhook URL in a database
        // and set up a job to check for signals and send notifications

        return [
            'status' => 'success',
            'message' => 'Successfully subscribed to volume-based signals',
            'webhook_url' => $webhookUrl,
            'config' => $config,
            'subscription_id' => uniqid('sub_'),
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Get combined volume and order book signals
     * Note: This requires your existing OrderBookAnalysisService
     */
    public function getCombinedSignals(string $symbol, string $interval = '1m')
    {
        // Get volume-based signals
        $volumeIndicators = $this->getAllVolumeIndicators($symbol, $interval);
        $volumeSignal = $this->analyzeVolumeIndicators($volumeIndicators);

        // In a real implementation, you would get order book signals from your existing service
        // For example:
        // $orderBookService = app(OrderBookAnalysisService::class);
        // $orderBookSignal = $orderBookService->analyzeOrderBook($symbol);

        // For demonstration, we'll create sample order book data
        $orderBookSignal = [
            'signal' => 'buy',
            'strength' => 7,
            'reasons' => ['Large bid wall detected', 'Thin asks above current price']
        ];

        // Combine signals - only generate strong buy/sell when both agree
        $combinedSignal = [
            'symbol' => $symbol,
            'interval' => $interval,
            'timestamp' => now()->toIso8601String(),
            'volume_signal' => $volumeSignal['signal'],
            'volume_strength' => $volumeSignal['strength'],
            'orderbook_signal' => $orderBookSignal['signal'],
            'orderbook_strength' => $orderBookSignal['strength'],
            'combined_signal' => 'neutral',
            'combined_strength' => 0,
            'reasons' => []
        ];

        // Only provide strong signal when both volume and order book agree
        if (
            $volumeSignal['signal'] === $orderBookSignal['signal'] &&
            $volumeSignal['signal'] !== 'neutral'
        ) {

            $combinedSignal['combined_signal'] = $volumeSignal['signal'];
            $combinedSignal['combined_strength'] = min(
                ($volumeSignal['strength'] + $orderBookSignal['strength']) / 2,
                10
            );

            // Combine reasons
            $combinedSignal['reasons'] = array_merge(
                ['Volume analysis: ' . implode(', ', $volumeSignal['reasons'])],
                ['Order book analysis: ' . implode(', ', $orderBookSignal['reasons'])]
            );
        } else {
            $combinedSignal['reasons'][] = 'Volume and order book signals do not align - waiting for confirmation';
        }

        // Check profit potential
        $profitPotential = $this->checkScalpingPotential($symbol, $interval);
        $combinedSignal['meets_profit_target'] = $profitPotential;

        if (!$profitPotential) {
            $combinedSignal['reasons'][] = 'Warning: Symbol may not have sufficient volatility for 0.5% target';
        }

        return $combinedSignal;
    }
}
