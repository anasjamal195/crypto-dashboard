<?php

namespace App\Console\Commands\Supervisors\MasterProcessManagers;

use App\CommonHelpers;
use App\Services\PerformanceMonitoringService;
use App\Services\SupervisorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
        $pythonServerRestartAttempts = 0;

        $currentProcess = 'laravel_master_safety_worker';
        while (true) {

            // $pythonServerHealth = self::checkPythonServerHealth('http://209.38.95.33:5000/health', 15);


            // if (!$pythonServerHealth['success']) {
            //     $pythonServerRestartAttempts++;
            //     if ($pythonServerRestartAttempts > 3) {
            //         $remoteMonitor->stopSupervisorProcesses();
            //         CommonHelpers::addSafetyLog('STOPPED_ALL_PROCESSES', 'Python sdk server is down. Restart failed');
            //         SupervisorService::stop($currentProcess);
            //         break;
            //     }
            //     CommonHelpers::addSafetyLog('PYTHON_SERVER_RESTART_ATTEMPT', 'Python sdk server is down. Attempting Restart... ' . (3 - $pythonServerRestartAttempts) . ' attempts left');
            //     $remoteMonitor->restartSupervisorProcesses('hyperliquid-sdk');
            //     sleep(5);
            //     continue;
            // }


            // Check server Stats and Perform actions likewise
            $sqlStatus =   $remoteMonitor->getMysqlStatus();

            if ($sqlStatus['status'] !== 'running') {
                // Attempt Restart
                $mysqlRestartAttempts++;

                // Attempt restart 3 times and than stop all supervisor processes and perform server reboot (BETA)
                if ($mysqlRestartAttempts > 3) {
                    $remoteMonitor->stopSupervisorProcesses();
                    CommonHelpers::addSafetyLog('STOPPED_ALL_PROCESSES', 'SQL down on live site. Restart Failed!');
                    SupervisorService::stop($currentProcess);
                    break;
                }
                $remoteMonitor->startMysql();
                CommonHelpers::addSafetyLog('SQL_RESTART_ATTEMPT', 'SQL down on live site. Attempting restart. ' . (3 - $mysqlRestartAttempts) . ' attempts left');
                sleep(5);
                $sqlStatus =  $remoteMonitor->getMysqlStatus();
                if ($sqlStatus['status'] !== 'running') {
                    $url = "https://rocket.cryptoapis.store/master-process/handle/" . config('binance.process_manager_client_key');
                    $data = [
                        'action' => 'RESTART_MULTITHREAD',
                    ];
                    $response = Http::post($url, $data);
                    sleep(10);
                }
                continue;
            }



            // Check Worker Status
            $url = "https://rocket.cryptoapis.store/master-process/handle/" . config('binance.process_manager_client_key');
            $data = [
                'action' => 'CHECK_WORKER_STATUS',
            ];
            $response = Http::post($url, $data);

            foreach ($response['data'] as $worker) {


                $updatedAt = Carbon::parse($worker['updated_at'], 'Asia/Karachi');

                // Get the current time in the same timezone
                $currentTime = Carbon::now('Asia/Karachi');

                // Calculate the difference in seconds
                $diffInSeconds = abs($currentTime->diffInSeconds($updatedAt));
                if ($diffInSeconds >= 60) {
                    CommonHelpers::addSafetyLog('WORKER_STOPPED', 'A worker has stopped');
                    
                }
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


            CommonHelpers::delayS(2);
        }
    }






    public static function checkPythonServerHealth($url, $timeout)
    {
        $startTime = microtime(true);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Hyperliquid-Health-Monitor/1.0');

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000); // Convert to milliseconds

        return [
            'success' => $response !== false && $httpCode === 200,
            'data' => $response,
            'http_code' => $httpCode,
            'error' => $error,
            'response_time' => $responseTime
        ];
    }
}
