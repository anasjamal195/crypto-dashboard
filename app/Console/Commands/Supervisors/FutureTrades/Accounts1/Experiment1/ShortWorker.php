<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts1\Experiment1;

use App\CommonHelpers;
use App\Services\FutureLiveTrades\LiveTradeSHORTFutureServiceEXP1;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShortWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-1-exp-1-short-worker';

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
                LiveTradeSHORTFutureServiceEXP1::performLiveTrades('FUTURE',1);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
