<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\SupportResistanceFakeBreakout;

use App\CommonHelpers;
use App\Services\SupportResistanceFakeBreakoutLiveTrades\SupportResistanceFakeBreakoutLiveTradesLong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LongWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-support-resistance-fake-breakout-long-worker';

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
                SupportResistanceFakeBreakoutLiveTradesLong::performLiveTrades('FUTURE', 2);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
