<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderBookStrategy
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl = 'https://api.binance.com';
    
    public function __construct($apiKey = null, $apiSecret = null)
    {
        $this->apiKey = $apiKey ?? config('services.binance.key');
        $this->apiSecret = $apiSecret ?? config('services.binance.secret');
    }
    
    /**
     * Get order book data for a specific symbol
     * 
     * @param string $symbol Trading pair (e.g., 'BTCUSDT')
     * @param int $limit Number of price levels (default: 100, max: 5000)
     * @return array|null Order book data or null on error
     */
    
    /**
     * Analyze order book to find potential long and short entry points
     * 
     * @param string $symbol Trading pair (e.g., 'BTCUSDT')
     * @param int $depth Order book depth to analyze
     * @param float $imbalanceThreshold Threshold for order imbalance (default: 1.5)
     * @param float $wallThreshold Threshold to identify walls (default: 2.0)
     * @return array Analysis results with potential entry points
     */
    public function analyzeOrderBook(
        string $symbol, 
        int $depth = 100, 
        float $imbalanceThreshold = 1.5, 
        float $wallThreshold = 2.0
    ): array {

        
        $orderBook = BinanceApiService::getOrderBook($symbol, $depth);
        if (!$orderBook) {
            return [
                'success' => false,
                'message' => 'Failed to fetch order book data'
            ];
        }
        
        $bids = $orderBook['bids']; // Buy orders
        $asks = $orderBook['asks']; // Sell orders
        
        // Calculate total volume for bids and asks
        $bidVolume = $this->calculateTotalVolume($bids);
        $askVolume = $this->calculateTotalVolume($asks);
        
        // Calculate volume imbalance ratio
        $volumeImbalance = $bidVolume / max(0.001, $askVolume); // Avoid division by zero
        
        // Identify support and resistance levels (walls)
        $supportLevels = $this->identifyWalls($bids, $wallThreshold);
        $resistanceLevels = $this->identifyWalls($asks, $wallThreshold);
        
        // Identify areas of thin liquidity (potential breakout points)
        $thinLiquidityAreas = $this->identifyThinLiquidity($bids, $asks);
        
        // Generate trading signals
        $signals = $this->generateSignals(
            $symbol,
            $volumeImbalance, 
            $imbalanceThreshold, 
            $supportLevels, 
            $resistanceLevels, 
            $thinLiquidityAreas
        );
        
        return [
            'success' => true,
            'symbol' => $symbol,
            'timestamp' => time(),
            'analysis' => [
                'bid_volume' => $bidVolume,
                'ask_volume' => $askVolume,
                'volume_imbalance' => $volumeImbalance,
                'support_levels' => $supportLevels,
                'resistance_levels' => $resistanceLevels,
                'thin_liquidity_areas' => $thinLiquidityAreas
            ],
            'signals' => $signals
        ];
    }
    
    /**
     * Calculate total volume for a set of orders
     * 
     * @param array $orders Array of [price, quantity] pairs
     * @return float Total volume
     */
    private function calculateTotalVolume(array $orders): float
    {
        $volume = 0;
        
        foreach ($orders as $order) {
            $volume += floatval($order[1]);
        }
        
        return $volume;
    }
    
    /**
     * Identify price levels with significantly larger orders (walls)
     * 
     * @param array $orders Array of [price, quantity] pairs
     * @param float $threshold Multiplier threshold to identify walls
     * @return array Price levels with walls and their volumes
     */
    private function identifyWalls(array $orders, float $threshold): array
    {
        if (empty($orders)) {
            return [];
        }
        
        // Calculate average volume
        $totalVolume = $this->calculateTotalVolume($orders);
        $averageVolume = $totalVolume / count($orders);
        
        // Find walls (price levels with volume > threshold * average)
        $walls = [];
        foreach ($orders as $order) {
            $price = floatval($order[0]);
            $volume = floatval($order[1]);
            
            if ($volume > ($averageVolume * $threshold)) {
                $walls[] = [
                    'price' => $price,
                    'volume' => $volume,
                    'strength' => $volume / $averageVolume // Relative strength of the wall
                ];
            }
        }
        
        // Sort walls by strength (descending)
        usort($walls, function($a, $b) {
            return $b['strength'] <=> $a['strength'];
        });
        
        // Return top 5 walls or fewer if less exist
        return array_slice($walls, 0, 5);
    }
    
    /**
     * Identify areas with thin liquidity (gaps in the order book)
     * 
     * @param array $bids Array of bid orders [price, quantity]
     * @param array $asks Array of ask orders [price, quantity]
     * @return array Areas with thin liquidity
     */
    private function identifyThinLiquidity(array $bids, array $asks): array
    {
        $thinAreas = [];
        
        // Find the highest bid and lowest ask
        $highestBid = floatval($bids[0][0]);
        $lowestAsk = floatval($asks[0][0]);
        
        // Current spread
        $spread = $lowestAsk - $highestBid;
        
        // Check for gaps in bids
        for ($i = 0; $i < count($bids) - 1; $i++) {
            $currentPrice = floatval($bids[$i][0]);
            $nextPrice = floatval($bids[$i + 1][0]);
            $gap = $currentPrice - $nextPrice;
            
            // If gap is significant (more than 2x the spread)
            if ($gap > (2 * $spread)) {
                $thinAreas[] = [
                    'type' => 'bid_gap',
                    'start_price' => $nextPrice,
                    'end_price' => $currentPrice,
                    'gap_size' => $gap,
                    'relative_size' => $gap / $spread
                ];
            }
        }
        
        // Check for gaps in asks
        for ($i = 0; $i < count($asks) - 1; $i++) {
            $currentPrice = floatval($asks[$i][0]);
            $nextPrice = floatval($asks[$i + 1][0]);
            $gap = $nextPrice - $currentPrice;
            
            // If gap is significant (more than 2x the spread)
            if ($gap > (2 * $spread)) {
                $thinAreas[] = [
                    'type' => 'ask_gap',
                    'start_price' => $currentPrice,
                    'end_price' => $nextPrice,
                    'gap_size' => $gap,
                    'relative_size' => $gap / $spread
                ];
            }
        }
        
        // Sort by relative gap size (descending)
        usort($thinAreas, function($a, $b) {
            return $b['relative_size'] <=> $a['relative_size'];
        });
        
        return array_slice($thinAreas, 0, 5);
    }
    
    /**
     * Generate trading signals based on order book analysis
     * 
     * @param string $symbol Trading pair
     * @param float $volumeImbalance Ratio of bid volume to ask volume
     * @param float $imbalanceThreshold Threshold for significant imbalance
     * @param array $supportLevels Identified support levels
     * @param array $resistanceLevels Identified resistance levels
     * @param array $thinLiquidityAreas Areas with thin liquidity
     * @return array Trading signals
     */
    private function generateSignals(
        string $symbol,
        float $volumeImbalance, 
        float $imbalanceThreshold, 
        array $supportLevels, 
        array $resistanceLevels, 
        array $thinLiquidityAreas
    ): array {
        $signals = [
            'long' => [
                'entry_points' => [],
                'strength' => 0,
                'reasoning' => []
            ],
            'short' => [
                'entry_points' => [],
                'strength' => 0,
                'reasoning' => []
            ]
        ];
        
        // Volume imbalance signals
        if ($volumeImbalance > $imbalanceThreshold) {
            // Strong buying pressure
            $signals['long']['strength'] += 2;
            $signals['long']['reasoning'][] = "Strong buying pressure: bid/ask ratio of {$volumeImbalance}";

        } elseif ($volumeImbalance < (1 / $imbalanceThreshold)) {
            // Strong selling pressure
            $signals['short']['strength'] += 2;
            $signals['short']['reasoning'][] = "Strong selling pressure: bid/ask ratio of {$volumeImbalance}";
        }
        
        // Support level signals for long positions
        if (!empty($supportLevels)) {
            $topSupport = $supportLevels[0];
            $signals['long']['entry_points'][] = [
                'price' => $topSupport['price'],
                'type' => 'support',
                'confidence' => min(5, $topSupport['strength'])
            ];
            $signals['long']['strength'] += min(3, $topSupport['strength'] / 2);
            $signals['long']['reasoning'][] = "Strong support wall at {$topSupport['price']} with strength {$topSupport['strength']}";
        }
        
        // Resistance level signals for short positions
        if (!empty($resistanceLevels)) {
            $topResistance = $resistanceLevels[0];
            $signals['short']['entry_points'][] = [
                'price' => $topResistance['price'],
                'type' => 'resistance',
                'confidence' => min(5, $topResistance['strength'])
            ];
            $signals['short']['strength'] += min(3, $topResistance['strength'] / 2);
            $signals['short']['reasoning'][] = "Strong resistance wall at {$topResistance['price']} with strength {$topResistance['strength']}";
        }
        
        // Thin liquidity areas for potential breakouts
        foreach ($thinLiquidityAreas as $area) {
            if ($area['type'] === 'ask_gap' && $area['relative_size'] > 3) {
                // Potential for upward breakout through thin ask liquidity
                $signals['long']['entry_points'][] = [
                    'price' => $area['start_price'],
                    'type' => 'breakout',
                    'confidence' => min(4, $area['relative_size'] / 2)
                ]; 
                $signals['long']['strength'] += 1;
                $signals['long']['reasoning'][] = "Potential upward breakout point at {$area['start_price']} due to thin sell orders";
            } elseif ($area['type'] === 'bid_gap' && $area['relative_size'] > 3) {
                // Potential for downward breakout through thin bid liquidity
                $signals['short']['entry_points'][] = [
                    'price' => $area['start_price'],
                    'type' => 'breakout',
                    'confidence' => min(4, $area['relative_size'] / 2)
                ];
                $signals['short']['strength'] += 1;
                $signals['short']['reasoning'][] = "Potential downward breakout point at {$area['start_price']} due to thin buy orders";
            }
        }
        
        // Normalize signal strength to 0-10 scale
        $signals['long']['strength'] = min(10, $signals['long']['strength']);
        $signals['short']['strength'] = min(10, $signals['short']['strength']);
        
        // Final recommendation
        if ($signals['long']['strength'] > $signals['short']['strength'] + 2) {
            $signals['recommendation'] = 'LONG';
        } elseif ($signals['short']['strength'] > $signals['long']['strength'] + 2) {
            $signals['recommendation'] = 'SHORT';
        } else {
            $signals['recommendation'] = 'NEUTRAL';
        }
        
        return $signals;
    }
    
    /**
     * Get real-time trading recommendation for a symbol
     * 
     * @param string $symbol Trading pair
     * @return array Trading recommendation
     */
    public function getTradingRecommendation(string $symbol): array
    {
        $analysis = $this->analyzeOrderBook($symbol);
        
        if (!$analysis['success']) {
            return $analysis;
        }
        
        $signals = $analysis['signals'];
        
        return [
            'symbol' => $symbol,
            'timestamp' => time(),
            'recommendation' => $signals['recommendation'],
            'long_strength' => $signals['long']['strength'],
            'short_strength' => $signals['short']['strength'],
            'entry_points' => [
                'long' => $signals['long']['entry_points'],
                'short' => $signals['short']['entry_points']
            ],
            'reasoning' => [
                'long' => $signals['long']['reasoning'],
                'short' => $signals['short']['reasoning']
            ],
            'raw_analysis' => $analysis['analysis']
        ];
    }
}