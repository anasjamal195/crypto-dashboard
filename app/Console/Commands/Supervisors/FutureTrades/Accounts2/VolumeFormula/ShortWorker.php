<?php

namespace App\Console\Commands\Supervisors\FutureTrades\Accounts2\VolumeFormula;

use App\CommonHelpers;
use App\Services\VolumeFormula\VolumeFormulaShort;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShortWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:acc-2-volume-short-worker';

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
                VolumeFormulaShort::performLiveTrades('FUTURE', 2);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMS(10);
        }
    }
}
