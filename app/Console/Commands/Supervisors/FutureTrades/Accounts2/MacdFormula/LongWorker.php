<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\MacdFormula;

use App\CommonHelpers;
use App\Services\Exp2\LiveTradeLongFutureServiceEXP2;
use App\Services\FutureLiveTrades\LiveTradeLONGFutureServiceEXP1;
use App\Services\MacdFormula\MacdFormulaLong;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LongWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-macd-long-worker';

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
                MacdFormulaLong::performLiveTrades('FUTURE', 2);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
