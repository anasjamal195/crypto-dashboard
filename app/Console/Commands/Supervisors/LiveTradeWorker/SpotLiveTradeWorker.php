<?php

namespace App\Console\Commands\Supervisors\LiveTradeWorker;

use App\CommonHelpers;
use App\Services\LiveTradeService;
use App\Services\MailerService;
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
        MailerService::sendWorkerEmail('spot_live_trader');
        while (true) {
            try {
                LiveTradeService::performLiveTrades('SPOT');
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(500);
        }
    }
}
