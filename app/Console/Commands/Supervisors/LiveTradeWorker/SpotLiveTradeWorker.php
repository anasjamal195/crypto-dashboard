<?php

namespace App\Console\Commands\Supervisors\LiveTradeWorker;

use App\Services\LiveTradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SpotLiveTradeWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:spot-live-trade-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform Live Trades';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            try {
                LiveTradeService::performLiveTrades('SPOT');
                usleep(200000);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
