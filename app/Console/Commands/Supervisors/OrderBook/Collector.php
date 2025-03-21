<?php

namespace App\Console\Commands\Supervisors\OrderBook;

use App\Services\OrderBookCollectorService;
use App\Services\OrderBookStrategy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Collector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:collector';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbols  = DB::table('coins')->where('market', 'FUTURE')->pluck('symbol')->toArray();

        $interval = 300;
        $depth = 100;
        $cleanup = false;
        $daysToKeep = 2;

        $this->info('Starting order book data collection');
        $this->info('Monitoring symbols: ' . implode(', ', $symbols));
        $this->info('Collection interval: ' . $interval . ' seconds');
        $collector = new OrderBookCollectorService(new OrderBookStrategy());
        // Continuous collection loop
        while (true) {
            $startTime = now();
            $this->info('Collecting data at: ' . $startTime->toDateTimeString());

            try {
                // Collect data for all symbols
                $results = $collector->collectForMultipleSymbols($symbols, $depth);

                // Log results
                foreach ($results as $symbol => $success) {
                    if ($success) {
                        $this->info("✓ Collected data for {$symbol}");
                    } else {
                        $this->error("✗ Failed to collect data for {$symbol}");
                    }
                }

                // Perform cleanup if enabled
                if ($cleanup) {
                    $now = Carbon::now();
                    // Only clean up once a day (at midnight)
                    if ($now->hour === 0 && $now->minute < 5) {
                        $this->info('Performing cleanup of old data...');
                        $deleted = $collector->purgeOldSnapshots($daysToKeep);
                        $this->info("Cleaned up {$deleted} old records");
                    }
                }
            } catch (\Exception $e) {
                $this->error('Error during collection: ' . $e->getMessage());
            }

            // Calculate how long to sleep
            $endTime = now();
            $processingTime = $endTime->diffInSeconds($startTime);
            $sleepTime = max(1, $interval - $processingTime);

            $this->info("Processing took {$processingTime} seconds. Sleeping for {$sleepTime} seconds...");
            sleep($sleepTime);
        }
    }
}
