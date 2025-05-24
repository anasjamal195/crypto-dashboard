<?php

namespace App\Console\Commands\Supervisors\MasterProcessManagers;

use App\CommonHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageProcesses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:manage-master-processes';

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

            $accounts = DB::table('accounts')->where('is_active', true)->where('account_id', 1)->get();

            foreach ($accounts as $account) {
                try {
                    // 1) Dump Fresh Live Trades data in DB
                    CommonHelpers::updateLiveTradesMasterTable($account);
                   

                    // // 2) Check for Staled Workers and send restart command to them
                    CommonHelpers::handleStaleTrades($account);

                    // 3) Check for staled Trades and send closing command to them
                } catch (\Throwable $th) {
                    $this->error($th->getMessage());
                    // dd($th);
                }
            }


            CommonHelpers::delayMS(100);
        }
    }
}
