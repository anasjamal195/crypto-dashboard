<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BlockchainTradingSignalService
{
    private const BLOCKCHAIN_METRICS_THRESHOLDS = [
        'exchange_outflow_threshold' => 10000, // 10,000 BTC
        'exchange_inflow_threshold' => 8000,   // 8,000 BTC
        'utxo_hodl_threshold' => 0.9,          // 90% of UTXOs held
        'mempool_congestion_threshold' => 50000, // 50,000 pending transactions
        'miner_accumulation_threshold' => 100,  // 100 BTC accumulated
        'whale_threshold' => 100,               // 100 BTC whale transactions
        'volume_spike_threshold' => 2.0,        // 2x average volume
    ];

    private const BASE_URL = 'https://api.bitnode.io/api/v1';
    private const CACHE_DURATION = 300; // 5 minutes cache
    private const SHORT_CACHE_DURATION = 60; // 1 minute for fast-changing data

    /**
     * Fetch exchange flows for a specific symbol
     */
    public function fetchExchangeFlows(string $symbol): array
    {
        $cacheKey = "exchange_flows_{$symbol}_v2";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/blockchain/exchange-flows', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_24h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'inflow' => $data['total_inflow'] ?? 0,
                        'outflow' => $data['total_outflow'] ?? 0,
                        'net_flow' => ($data['total_outflow'] ?? 0) - ($data['total_inflow'] ?? 0),
                        'largest_transactions' => $data['largest_transactions'] ?? [],
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch exchange flows: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Exchange Flows Fetch Error: ' . $e->getMessage());
                return [
                    'inflow' => 0,
                    'outflow' => 0,
                    'net_flow' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch UTXO (Unspent Transaction Output) metrics
     */
    public function fetchUTXOMetrics(string $symbol): array
    {
        $cacheKey = "utxo_metrics_{$symbol}_v2";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/blockchain/utxo-metrics', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_30d'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'total_utxos' => $data['total_utxos'] ?? 0,
                        'hodl_percentage' => $data['hodl_percentage'] ?? 0,
                        'active_utxos' => $data['active_utxos'] ?? 0,
                        'distribution' => $data['utxo_distribution'] ?? [],
                        'age_distribution' => $data['age_distribution'] ?? [],
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch UTXO metrics: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('UTXO Metrics Fetch Error: ' . $e->getMessage());
                return [
                    'total_utxos' => 0,
                    'hodl_percentage' => 0,
                    'active_utxos' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Mempool activity metrics
     */
    public function fetchMempoolActivity(string $symbol): array
    {
        $cacheKey = "mempool_activity_{$symbol}_v2";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/blockchain/mempool', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_1h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'pending_transactions' => $data['total_pending_txs'] ?? 0,
                        'total_volume' => $data['total_mempool_volume'] ?? 0,
                        'average_fee' => $data['average_tx_fee'] ?? 0,
                        'high_priority_fee' => $data['high_priority_fee'] ?? 0,
                        'transaction_size_distribution' => $data['tx_size_distribution'] ?? [],
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch mempool activity: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Mempool Activity Fetch Error: ' . $e->getMessage());
                return [
                    'pending_transactions' => 0,
                    'total_volume' => 0,
                    'average_fee' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Miner-related metrics
     */
    public function fetchMinerMetrics(string $symbol): array
    {
        $cacheKey = "miner_metrics_{$symbol}_v2";

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/blockchain/miner-metrics', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_24h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'total_revenue' => $data['total_mining_revenue'] ?? 0,
                        'difficulty' => $data['mining_difficulty'] ?? 0,
                        'hash_rate' => $data['network_hash_rate'] ?? 0,
                        'block_rewards' => $data['block_rewards'] ?? [],
                        'accumulation' => $data['miner_accumulation'] ?? 0,
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch miner metrics: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Miner Metrics Fetch Error: ' . $e->getMessage());
                return [
                    'total_revenue' => 0,
                    'difficulty' => 0,
                    'hash_rate' => 0,
                    'accumulation' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Wallet activity metrics
     */
    public function fetchWalletActivity(string $symbol): array
{
    $cacheKey = "wallet_activity_{$symbol}_v2";

    return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($symbol) {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json'
            ])->timeout(10)->get(self::BASE_URL . '/blockchain/wallet-activity', [
                'symbol' => $symbol,
                'timeframe' => 'last_30d'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $whaleCount = count(array_filter($data['whale_transactions'] ?? [], function($tx) {
                    return ($tx['amount'] ?? 0) >= self::BLOCKCHAIN_METRICS_THRESHOLDS['whale_threshold'];
                }));
                
                return [
                    'active_wallets' => $data['total_active_wallets'] ?? 0,
                    'whale_transactions' => $data['whale_transactions'] ?? [],
                    'whale_count' => $whaleCount,
                    'wallet_distribution' => $data['wallet_balance_distribution'] ?? [],
                    'new_wallet_creation_rate' => $data['new_wallet_rate'] ?? 0, // Changed from new_wallet_creation_rate to new_wallet_rate
                    'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                ];
            }

            throw new \Exception('Failed to fetch wallet activity: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Wallet Activity Fetch Error: ' . $e->getMessage());
            return [
                'active_wallets' => 0,
                'whale_transactions' => [],
                'whale_count' => 0,
                'wallet_distribution' => [],
                'new_wallet_creation_rate' => 0, // Ensure this is always set
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ];
        }
    });
}

    /**
     * Fetch Futures Open Interest
     */
    public function fetchFuturesOpenInterest(string $symbol): array
    {
        $cacheKey = "futures_oi_{$symbol}_v2";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/derivatives/futures-open-interest', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_24h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $totalOI = $data['total_open_interest'] ?? 0;
                    $longOI = $data['long_open_interest'] ?? 0;
                    $shortOI = $data['short_open_interest'] ?? 0;
                    
                    return [
                        'total_oi' => $totalOI,
                        'long_oi' => $longOI,
                        'short_oi' => $shortOI,
                        'long_ratio' => $totalOI > 0 ? $longOI / $totalOI : 0,
                        'short_ratio' => $totalOI > 0 ? $shortOI / $totalOI : 0,
                        'oi_change' => $data['open_interest_change'] ?? 0,
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch futures open interest: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Futures Open Interest Fetch Error: ' . $e->getMessage());
                return [
                    'total_oi' => 0,
                    'long_oi' => 0,
                    'short_oi' => 0,
                    'long_ratio' => 0,
                    'short_ratio' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Funding Rates
     */
    public function fetchFundingRates(string $symbol): array
    {
        $cacheKey = "funding_rates_{$symbol}_v2";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/derivatives/funding-rates', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_8h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $rates = $data['historical_rates'] ?? [];
                    $avgRate = count($rates) > 0 ? array_sum($rates) / count($rates) : 0;
                    
                    return [
                        'current_rate' => $data['current_funding_rate'] ?? 0,
                        'historical_rates' => $rates,
                        'average_rate' => $avgRate,
                        'period' => $data['funding_period'] ?? '8h',
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch funding rates: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Funding Rates Fetch Error: ' . $e->getMessage());
                return [
                    'current_rate' => 0,
                    'historical_rates' => [],
                    'average_rate' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Order Book Dynamics
     */
    public function fetchOrderBookDynamics(string $symbol): array
    {
        $cacheKey = "order_book_{$symbol}_v2";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/market/order-book', [
                    'symbol' => $symbol,
                    'depth' => 20
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $bidVolume = $data['total_bid_volume'] ?? 0;
                    $askVolume = $data['total_ask_volume'] ?? 0;
                    $totalVolume = $bidVolume + $askVolume;
                    
                    return [
                        'bid_ask_ratio' => $askVolume > 0 ? $bidVolume / $askVolume : 1,
                        'total_bid_volume' => $bidVolume,
                        'total_ask_volume' => $askVolume,
                        'bid_dominance' => $totalVolume > 0 ? $bidVolume / $totalVolume : 0.5,
                        'ask_dominance' => $totalVolume > 0 ? $askVolume / $totalVolume : 0.5,
                        'top_bids' => $data['top_bids'] ?? [],
                        'top_asks' => $data['top_asks'] ?? [],
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch order book dynamics: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Order Book Dynamics Fetch Error: ' . $e->getMessage());
                return [
                    'bid_ask_ratio' => 1,
                    'total_bid_volume' => 0,
                    'total_ask_volume' => 0,
                    'bid_dominance' => 0.5,
                    'ask_dominance' => 0.5,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Fetch Volume Profiles
     */
    public function fetchVolumeProfiles(string $symbol): array
    {
        $cacheKey = "volume_profile_{$symbol}_v2";

        return Cache::remember($cacheKey, self::SHORT_CACHE_DURATION, function () use ($symbol) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->timeout(10)->get(self::BASE_URL . '/market/volume-profile', [
                    'symbol' => $symbol,
                    'timeframe' => 'last_24h'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $volumeDirection = ($data['volume_direction'] ?? 0) <=> 0;
                    
                    return [
                        'current_volume' => $data['current_volume'] ?? 0,
                        'average_volume' => $data['average_volume'] ?? 0,
                        'volume_spike' => $data['average_volume'] > 0 
                            ? ($data['current_volume'] ?? 0) / $data['average_volume'] 
                            : 1,
                        'volume_direction' => $volumeDirection,
                        'volume_distribution' => $data['volume_distribution'] ?? [],
                        'timestamp' => $data['timestamp'] ?? now()->toIso8601String()
                    ];
                }

                throw new \Exception('Failed to fetch volume profiles: ' . $response->status());
            } catch (\Exception $e) {
                Log::error('Volume Profiles Fetch Error: ' . $e->getMessage());
                return [
                    'current_volume' => 0,
                    'average_volume' => 0,
                    'volume_spike' => 1,
                    'volume_direction' => 0,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String()
                ];
            }
        });
    }

    /**
     * Generate comprehensive trading signals based on blockchain and market data
     */
    public function generateBlockchainTradingSignal(string $symbol): array
    {
        try {
            // Fetch comprehensive blockchain and market data
            $blockchainData = $this->fetchBlockchainData($symbol);
            $marketData = $this->fetchMarketData($symbol);

            // Analyze on-chain metrics with weighted scores
            $onChainSignals = $this->analyzeOnChainMetrics($blockchainData);

            // Analyze market dynamics with weighted scores
            $marketSignals = $this->analyzeMarketDynamics($marketData);

            // Combine and generate final signal with sentiment analysis
            $finalSignal = $this->generateFinalSignal($onChainSignals, $marketSignals);

            return [
                'symbol' => $symbol,
                'timestamp' => now()->toIso8601String(),
                'signal' => [
                    'type' => $finalSignal['type'],
                    'confidence' => $finalSignal['confidence'],
                    'reasoning' => $finalSignal['reasoning'],
                    'sentiment_score' => $finalSignal['sentiment_score']
                ],
                'raw_metrics' => [
                    'blockchain_metrics' => $this->formatBlockchainMetrics($blockchainData),
                    'market_metrics' => $this->formatMarketMetrics($marketData),
                    'signal_breakdown' => [
                        'on_chain_signals' => $onChainSignals,
                        'market_signals' => $marketSignals
                    ]
                ],
                'analysis_metadata' => [
                    'data_sources' => [
                        'blockchain' => 'BitNode.io API',
                        'market_data' => 'BitNode.io Market Endpoints'
                    ],
                    'analysis_version' => '2.0.0',
                    'thresholds' => self::BLOCKCHAIN_METRICS_THRESHOLDS
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Blockchain Trading Signal Error: ' . $e->getMessage());
            return [
                'error' => 'Unable to generate blockchain trading signal',
                'message' => $e->getMessage(),
                'timestamp' => now()->toIso8601String()
            ];
        }
    }

    /**
     * Format blockchain metrics for output
     */
    private function formatBlockchainMetrics(array $blockchainData): array
    {

        return [
            'exchange_flows' => [
                'total_inflow' => $blockchainData['exchange_flows']['inflow'],
                'total_outflow' => $blockchainData['exchange_flows']['outflow'],
                'net_flow' => $blockchainData['exchange_flows']['net_flow'],
                'largest_transactions' => count($blockchainData['exchange_flows']['largest_transactions']),
                'timestamp' => $blockchainData['exchange_flows']['timestamp']
            ],

            'utxo_analysis' => [
                'total_utxos' => $blockchainData['utxo_metrics']['total_utxos'],
                'hodl_percentage' => $blockchainData['utxo_metrics']['hodl_percentage'] * 100,
                'active_utxos' => $blockchainData['utxo_metrics']['active_utxos'],
                'age_distribution' => $blockchainData['utxo_metrics']['age_distribution'],
                'timestamp' => $blockchainData['utxo_metrics']['timestamp']
            ],

            'mempool_data' => [
                'pending_transactions' => $blockchainData['mempool_activity']['pending_transactions'],
                'total_transaction_volume' => $blockchainData['mempool_activity']['total_volume'],
                'average_transaction_fee' => $blockchainData['mempool_activity']['average_fee'],
                'high_priority_fee' => $blockchainData['mempool_activity']['high_priority_fee'],
                'timestamp' => $blockchainData['mempool_activity']['timestamp']
            ],
            
            'miner_metrics' => [
                'total_mining_revenue' => $blockchainData['miner_metrics']['total_revenue'],
                'mining_difficulty' => $blockchainData['miner_metrics']['difficulty'],
                'hash_rate' => $blockchainData['miner_metrics']['hash_rate'],
                'accumulation' => $blockchainData['miner_metrics']['accumulation'],
                'timestamp' => $blockchainData['miner_metrics']['timestamp']
            ],

            'wallet_activity' => [
                'total_active_wallets' => $blockchainData['wallet_activity']['active_wallets'] ?? 0,
                'whale_movements' => $blockchainData['wallet_activity']['whale_count'] ?? 0,
                'new_wallets' => $blockchainData['wallet_activity']['new_wallet_creation_rate'] ?? 0, // Added null coalescing
                'timestamp' => $blockchainData['wallet_activity']['timestamp'] ?? now()->toIso8601String()
            ]
        ];
    }

    /**
     * Format market metrics for output
     */
    private function formatMarketMetrics(array $marketData): array
    {
        return [
            'futures_data' => [
                'total_open_interest' => $marketData['futures_open_interest']['total_oi'],
                'long_open_interest' => $marketData['futures_open_interest']['long_oi'],
                'short_open_interest' => $marketData['futures_open_interest']['short_oi'],
                'long_ratio' => $marketData['futures_open_interest']['long_ratio'] * 100,
                'short_ratio' => $marketData['futures_open_interest']['short_ratio'] * 100,
                'oi_change' => $marketData['futures_open_interest']['oi_change'],
                'timestamp' => $marketData['futures_open_interest']['timestamp']
            ],
            'funding_rates' => [
                'current_funding_rate' => $marketData['funding_rates']['current_rate'] * 100,
                'average_funding_rate' => $marketData['funding_rates']['average_rate'] * 100,
                'funding_period' => $marketData['funding_rates']['period'],
                'timestamp' => $marketData['funding_rates']['timestamp']
            ],
            'order_book_metrics' => [
                'bid_ask_ratio' => $marketData['order_book_dynamics']['bid_ask_ratio'],
                'total_bid_volume' => $marketData['order_book_dynamics']['total_bid_volume'],
                'total_ask_volume' => $marketData['order_book_dynamics']['total_ask_volume'],
                'bid_dominance' => $marketData['order_book_dynamics']['bid_dominance'] * 100,
                'ask_dominance' => $marketData['order_book_dynamics']['ask_dominance'] * 100,
                'timestamp' => $marketData['order_book_dynamics']['timestamp']
            ],
            'volume_profiles' => [
                'current_volume' => $marketData['volume_profiles']['current_volume'],
                'average_volume' => $marketData['volume_profiles']['average_volume'],
                'volume_spike_multiplier' => $marketData['volume_profiles']['volume_spike'],
                'volume_direction' => $marketData['volume_profiles']['volume_direction'],
                'timestamp' => $marketData['volume_profiles']['timestamp']
            ]
        ];
    }

    /**
     * Fetch comprehensive blockchain data from BitNode
     */
    private function fetchBlockchainData(string $symbol): array
    {
        return [
            'exchange_flows' => $this->fetchExchangeFlows($symbol),
            'utxo_metrics' => $this->fetchUTXOMetrics($symbol),
            'mempool_activity' => $this->fetchMempoolActivity($symbol),
            'miner_metrics' => $this->fetchMinerMetrics($symbol),
            'wallet_activity' => $this->fetchWalletActivity($symbol)
        ];
    }

    /**
     * Fetch market-related data
     */
    private function fetchMarketData(string $symbol): array
    {
        return [
            'futures_open_interest' => $this->fetchFuturesOpenInterest($symbol),
            'funding_rates' => $this->fetchFundingRates($symbol),
            'order_book_dynamics' => $this->fetchOrderBookDynamics($symbol),
            'volume_profiles' => $this->fetchVolumeProfiles($symbol)
        ];
    }

    /**
     * Analyze on-chain metrics to generate blockchain-based signals
     */
    private function analyzeOnChainMetrics(array $blockchainData): array
    {
        $signals = [
            'long_triggers' => 0,
            'short_triggers' => 0,
            'neutral_triggers' => 0,
            'weighted_score' => 0,
            'details' => []
        ];
        // Exchange Outflows Analysis (Weight: 3)
        $netFlow = $blockchainData['exchange_flows']['net_flow'];
        if ($netFlow > self::BLOCKCHAIN_METRICS_THRESHOLDS['exchange_outflow_threshold']) {
            $signals['long_triggers'] += 3;
            $signals['weighted_score'] += 3;
            $signals['details'][] = 'Strong exchange outflows (Bullish)';
        } elseif ($netFlow < -self::BLOCKCHAIN_METRICS_THRESHOLDS['exchange_inflow_threshold']) {
            $signals['short_triggers'] += 3;
            $signals['weighted_score'] -= 3;
            $signals['details'][] = 'Significant exchange inflows (Bearish)';
        }

        // UTXO Analysis (Weight: 2)
        $hodlPercentage = $blockchainData['utxo_metrics']['hodl_percentage'];
        if ($hodlPercentage > self::BLOCKCHAIN_METRICS_THRESHOLDS['utxo_hodl_threshold']) {
            $signals['long_triggers'] += 2;
            $signals['weighted_score'] += 2;
            $signals['details'][] = 'High HODL percentage (Bullish)';
        } elseif ($hodlPercentage < 0.5) {
            $signals['short_triggers'] += 1;
            $signals['weighted_score'] -= 1;
            $signals['details'][] = 'Low HODL percentage (Bearish)';
        }

        // Mempool Activity (Weight: 2)
        $pendingTx = $blockchainData['mempool_activity']['pending_transactions'];
        if ($pendingTx < self::BLOCKCHAIN_METRICS_THRESHOLDS['mempool_congestion_threshold'] / 2) {
            $signals['long_triggers'] += 2;
            $signals['weighted_score'] += 2;
            $signals['details'][] = 'Low mempool congestion (Bullish)';
        } elseif ($pendingTx > self::BLOCKCHAIN_METRICS_THRESHOLDS['mempool_congestion_threshold']) {
            $signals['short_triggers'] += 2;
            $signals['weighted_score'] -= 2;
            $signals['details'][] = 'High mempool congestion (Bearish)';
        }

        // Miner Metrics (Weight: 2)
        $minerAccumulation = $blockchainData['miner_metrics']['accumulation'];
        if ($minerAccumulation > self::BLOCKCHAIN_METRICS_THRESHOLDS['miner_accumulation_threshold']) {
            $signals['long_triggers'] += 2;
            $signals['weighted_score'] += 2;
            $signals['details'][] = 'Miner accumulation (Bullish)';
        } elseif ($minerAccumulation < -self::BLOCKCHAIN_METRICS_THRESHOLDS['miner_accumulation_threshold']) {
            $signals['short_triggers'] += 2;
            $signals['weighted_score'] -= 2;
            $signals['details'][] = 'Miner distribution (Bearish)';
        }

        // Whale Activity (Weight: 3)
        $whaleCount = $blockchainData['wallet_activity']['whale_count'];
        if ($whaleCount > 10) { // More than 10 whale transactions
            // Whale buys are more bullish than whale sells are bearish
            $signals['long_triggers'] += 3;
            $signals['weighted_score'] += 3;
            $signals['details'][] = 'High whale activity (Direction uncertain but volatile)';
        }

        // Wallet Growth (Weight: 1)
        $walletGrowth = $blockchainData['wallet_activity']['new_wallet_creation_rate'] ?? 0; // Added null coalescing
        if ($walletGrowth > 1000) { // Example threshold
            $signals['long_triggers'] += 1;
            $signals['weighted_score'] += 1;
            $signals['details'][] = 'New wallet growth (Bullish)';
        }
    

        return $signals;
    }

    /**
     * Analyze market dynamics to generate trading signals
     */
    private function analyzeMarketDynamics(array $marketData): array
    {
        $signals = [
            'long_triggers' => 0,
            'short_triggers' => 0,
            'neutral_triggers' => 0,
            'weighted_score' => 0,
            'details' => []
        ];

        // Futures Open Interest (Weight: 3)
        $longRatio = $marketData['futures_open_interest']['long_ratio'];
        $shortRatio = $marketData['futures_open_interest']['short_ratio'];
        
        if ($longRatio > 0.7) {
            $signals['short_triggers'] += 3; // Overcrowded long
            $signals['weighted_score'] -= 3;
            $signals['details'][] = 'Overcrowded long positions (Bearish)';
        } elseif ($shortRatio > 0.7) {
            $signals['long_triggers'] += 3; // Overcrowded short
            $signals['weighted_score'] += 3;
            $signals['details'][] = 'Overcrowded short positions (Bullish)';
        }

        // Funding Rates (Weight: 2)
        $fundingRate = $marketData['funding_rates']['current_rate'];
        if ($fundingRate > 0.01) {
            $signals['short_triggers'] += 2;
            $signals['weighted_score'] -= 2;
            $signals['details'][] = 'High positive funding (Bearish)';
        } elseif ($fundingRate < -0.01) {
            $signals['long_triggers'] += 2;
            $signals['weighted_score'] += 2;
            $signals['details'][] = 'High negative funding (Bullish)';
        }

        // Order Book Dynamics (Weight: 2)
        $bidAskRatio = $marketData['order_book_dynamics']['bid_ask_ratio'];
        if ($bidAskRatio > 1.5) {
            $signals['long_triggers'] += 2;
            $signals['weighted_score'] += 2;
            $signals['details'][] = 'Strong bid dominance (Bullish)';
        } elseif ($bidAskRatio < 0.67) { // 1/1.5
            $signals['short_triggers'] += 2;
            $signals['weighted_score'] -= 2;
            $signals['details'][] = 'Strong ask dominance (Bearish)';
        }

        // Volume Profiles (Weight: 3)
        $volumeSpike = $marketData['volume_profiles']['volume_spike'];
        $volumeDirection = $marketData['volume_profiles']['volume_direction'];
        
        if ($volumeSpike > self::BLOCKCHAIN_METRICS_THRESHOLDS['volume_spike_threshold']) {
            if ($volumeDirection > 0) {
                $signals['long_triggers'] += 3;
                $signals['weighted_score'] += 3;
                $signals['details'][] = 'Volume spike with buying (Bullish)';
            } else {
                $signals['short_triggers'] += 3;
                $signals['weighted_score'] -= 3;
                $signals['details'][] = 'Volume spike with selling (Bearish)';
            }
        }

        return $signals;
    }

    /**
     * Generate final trading signal based on combined analyses
     */
    private function generateFinalSignal(array $onChainSignals, array $marketSignals): array
    {
        // Calculate total weighted scores
        $totalScore = $onChainSignals['weighted_score'] + $marketSignals['weighted_score'];
        
        // Calculate maximum possible score (for normalization)
        $maxPossibleScore = 15; // Sum of all weights (3+2+2+2+3+3+2+2+3)
        
        // Normalize to -1 (strong bearish) to +1 (strong bullish)
        $normalizedScore = $totalScore / $maxPossibleScore;
        
        // Calculate confidence (0-100%)
        $confidence = min(100, max(0, (abs($normalizedScore) * 100) + 20)); // Minimum 20% confidence
        
        // Determine signal type
        if ($normalizedScore > 0.2) {
            $signalType = 'strong_buy';
        } elseif ($normalizedScore > 0.05) {
            $signalType = 'buy';
        } elseif ($normalizedScore < -0.2) {
            $signalType = 'strong_sell';
        } elseif ($normalizedScore < -0.05) {
            $signalType = 'sell';
        } else {
            $signalType = 'neutral';
            $confidence = max(30, $confidence); // Cap neutral confidence at 30%
        }

        return [
            'type' => $signalType,
            'confidence' => round($confidence, 2),
            'sentiment_score' => round($normalizedScore, 4),
            'reasoning' => $this->generateSignalReasoning($onChainSignals, $marketSignals, $signalType)
        ];
    }

    /**
     * Generate detailed reasoning for the trading signal
     */
    private function generateSignalReasoning(array $onChainSignals, array $marketSignals, string $signalType): string
    {
        $reasons = ["Market analysis summary:"];
        
        // Add on-chain reasons
        if (!empty($onChainSignals['details'])) {
            $reasons[] = "On-chain indicators:";
            $reasons = array_merge($reasons, $onChainSignals['details']);
        }
        
        // Add market reasons
        if (!empty($marketSignals['details'])) {
            $reasons[] = "Market indicators:";
            $reasons = array_merge($reasons, $marketSignals['details']);
        }
        
        // Add summary based on signal type
        switch ($signalType) {
            case 'strong_buy':
                $reasons[] = "STRONG BUY: Multiple strong bullish indicators across on-chain and market data suggest significant upside potential.";
                break;
            case 'buy':
                $reasons[] = "BUY: Bullish indicators outweigh bearish signals, suggesting favorable buying conditions.";
                break;
            case 'strong_sell':
                $reasons[] = "STRONG SELL: Multiple strong bearish indicators across on-chain and market data suggest significant downside risk.";
                break;
            case 'sell':
                $reasons[] = "SELL: Bearish indicators outweigh bullish signals, suggesting caution or potential short opportunities.";
                break;
            default:
                $reasons[] = "NEUTRAL: Mixed signals with no clear directional bias. Market may be consolidating.";
        }
        
        $reasons[] = "Always consider risk management and additional factors before trading.";
        
        return implode("\n- ", $reasons);
    }
}