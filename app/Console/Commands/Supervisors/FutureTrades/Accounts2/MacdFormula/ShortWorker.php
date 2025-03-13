<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\MacdFormula;

use App\CommonHelpers;
use App\Services\Exp2\LiveTradeShortFutureServiceEXP2;
use App\Services\FutureLiveTrades\LiveTradeSHORTFutureServiceEXP1;
use App\Services\MacdFormula\MacdFormulaShort;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShortWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-macd-short-worker';

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
                MacdFormulaShort::performLiveTrades('FUTURE', 2);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
