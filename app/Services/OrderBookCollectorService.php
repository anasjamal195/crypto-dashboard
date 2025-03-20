<?php

namespace App\Services;

use App\Models\OrderBookSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderBookCollectorService
{
    protected $orderBookStrategy;

    /**
     * Create a new service instance.
     *
     * @param \App\Services\OrderBookStrategy $orderBookStrategy
     * @return void
     */
    public function __construct(OrderBookStrategy $orderBookStrategy)
    {
        $this->orderBookStrategy = $orderBookStrategy;
    }

    /**
     * Collect and store order book data for a specific symbol
     *
     * @param string $symbol
     * @param int $depth
     * @return \App\Models\OrderBookSnapshot|null
     */
    public function collectAndStore($symbol, $depth = 100)
    {
        try {
            // Get raw order book data
            $orderBookData = BinanceApiService::getOrderBook($symbol, $depth);
            if (!$orderBookData) {
                Log::error("Failed to fetch order book data for {$symbol}");
                return null;
            }

            // Analyze the order book
            $analysis = $this->orderBookStrategy->analyzeOrderBook($symbol, $depth);
            if (!$analysis['success']) {
                Log::error("Failed to analyze order book data for {$symbol}");
                return null;
            }


            // Extract data from analysis
            $analysisData = $analysis['analysis'];
            $signals = $analysis['signals'];

            // Create the snapshot record
            $snapshot = OrderBookSnapshot::create([
                'symbol' => $symbol,
                'snapshot_time' => Carbon::now(),
                'depth' => $depth,
                'raw_data' => $orderBookData,
                'bid_volume' => $analysisData['bid_volume'],
                'ask_volume' => $analysisData['ask_volume'],
                'volume_imbalance' => $analysisData['volume_imbalance'],
                'highest_bid' => isset($orderBookData['bids'][0]) ? $orderBookData['bids'][0][0] : null,
                'lowest_ask' => isset($orderBookData['asks'][0]) ? $orderBookData['asks'][0][0] : null,
                'spread' => isset($orderBookData['asks'][0]) && isset($orderBookData['bids'][0])
                    ? (float)$orderBookData['asks'][0][0] - (float)$orderBookData['bids'][0][0]
                    : null,
                'support_levels' => $analysisData['support_levels'],
                'resistance_levels' => $analysisData['resistance_levels'],
                'thin_liquidity_areas' => $analysisData['thin_liquidity_areas'],
                'signal' => $signals['recommendation'],
                'long_strength' => $signals['long']['strength'],
                'short_strength' => $signals['short']['strength'],
                'long_entry_points' => $signals['long']['entry_points'],
                'short_entry_points' => $signals['short']['entry_points'],
            ]);

            Log::info("Stored order book snapshot for {$symbol} with ID {$snapshot->id}");
            if ($snapshot->signal == 'LONG' || $snapshot->signal == 'SHORT') {
                MailerService::sendOrderBookSignalEmail($snapshot);
            }
            return $snapshot;
        } catch (\Exception $e) {
            Log::error("Error collecting order book data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Collect order book data for multiple symbols
     *
     * @param array $symbols
     * @param int $depth
     * @return array
     */
    public function collectForMultipleSymbols(array $symbols, $depth = 100)
    {
        $results = [];

        foreach ($symbols as $symbol) {
            $result = $this->collectAndStore($symbol, $depth);
            $results[$symbol] = $result ? true : false;
        }

        return $results;
    }

    /**
     * Purge old snapshots to manage database size
     * 
     * @param int $daysToKeep
     * @return int Number of deleted records
     */
    public function purgeOldSnapshots($daysToKeep = 2)
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        $count = OrderBookSnapshot::where('snapshot_time', '<', $cutoffDate)->delete();

        Log::info("Purged {$count} order book snapshots older than {$daysToKeep} days");
        return $count;
    }
}
