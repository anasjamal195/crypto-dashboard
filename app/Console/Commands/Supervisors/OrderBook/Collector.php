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

        $interval = 1;
        $depths = [
            1000 => null
        ];
        if (env('APP_NAME') === 'Crypto Api Bot (Development)') {
            $depths = [
                1000 => null,
            ];
        }

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

                $collector->collectForMultipleSymbols($symbols, $depths);



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


            sleep($interval);
        }
    }
}
