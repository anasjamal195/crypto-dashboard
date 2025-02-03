<?php

namespace App\Console\Commands\Supervisors\LiveTradeWorker;

use App\Services\LiveTradeFutureService\LiveTradeLONGFutureServiceEXP1;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FutureLONGWorkerEXP1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-long-worker-exp-1';

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
                LiveTradeLONGFutureServiceEXP1::performLiveTrades('FUTURE');
                usleep(200000);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
