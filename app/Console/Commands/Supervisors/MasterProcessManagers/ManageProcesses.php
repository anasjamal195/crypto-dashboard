<?php

namespace App\Console\Commands\Supervisors\MasterProcessManagers;

use App\CommonHelpers;
use App\Services\PerformanceMonitoringService;
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

        // Live Server SSH connection Module
        $remoteMonitor = new PerformanceMonitoringService('209.38.95.33', 'root', 'l@v1k2*l09g$m@xeiTp');

        $mysqlRestartAttempts = 0;
        $mysqlRestartAttemptsLimit = 3;

        while (true) {


            // Check server Stats and Perform actions likewise
            $sqlStatus =   $remoteMonitor->getMysqlStatus();

            if ($sqlStatus['status'] !== 'running') {
                // Attempt Restart
                $remoteMonitor->startMysql();
                $mysqlRestartAttempts++;

                // Attempt restart 3 times and than stop all supervisor processes and perform server reboot (BETA)
                if ($mysqlRestartAttempts > 3) {

                    $remoteMonitor->stopAllSupervisorProcesses();
                    break;
                }

                sleep(5);
                continue;
            }




            


            $accounts = DB::table('users')->where('is_active', true)->where('role', 'trader')->where('domain_name', '!=', 'egeniuscare.shop')->get();

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
