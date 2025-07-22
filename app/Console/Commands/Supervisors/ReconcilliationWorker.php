<?php

namespace App\Console\Commands\Supervisors;

use App\CommonHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcilliationWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reconcilliation-worker';

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


        $lastSystemTimestamp = CommonHelpers::getSettingsValue('last_check_rec_worker', time() * 1000);


        $internalTrades = DB::table('coin_report_safe_mode')->where('openingTimestamp','>=',$lastSystemTimestamp);



        dd($internalTrades);

        


    }
}
