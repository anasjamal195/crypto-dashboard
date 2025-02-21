<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\RsiBreakoutFormula;

use App\CommonHelpers;
use App\Services\RsiBreakoutFormula\RsiBreakoutFormulaLong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LongWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-rsi-breakout-long-worker';

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
                RsiBreakoutFormulaLong::performLiveTrades('FUTURE', 2);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
