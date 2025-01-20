<?php

namespace App\Console\Commands\Supervisors\DynamicTradesWorker;

use App\Services\DynamicTradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SpotDynamicTradeWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:spot-dynamic-trade-worker';

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
        while (true) {
            try {
                DynamicTradeService::checkDynamicTradesSPOT();
                usleep(10000); // 10ms delay
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
