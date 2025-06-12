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


            $pythonServerHealth = self::checkPythonServerHealth('http://209.38.95.33:5000/health', 15);

            if (!$pythonServerHealth['success']) {
                CommonHelpers::addSafetyLog('PYTHON_SERVER_DOWN', 'Python sdk server is down. Stopping all processes');
                $remoteMonitor->stopAllSupervisorProcesses();
                break;
            }


            // Check server Stats and Perform actions likewise
            $sqlStatus =   $remoteMonitor->getMysqlStatus();

            if ($sqlStatus['status'] !== 'running') {
                // Attempt Restart
                $remoteMonitor->startMysql();
                $mysqlRestartAttempts++;

                // Attempt restart 3 times and than stop all supervisor processes and perform server reboot (BETA)
                if ($mysqlRestartAttempts > 3) {
                    $remoteMonitor->stopAllSupervisorProcesses();
                    CommonHelpers::addSafetyLog('STOPPED_ALL_PROCESSES', 'SQL down on live site. Restart Failed!');
                    break;
                }
                CommonHelpers::addSafetyLog('SQL_RESTART_ATTEMPT_' . $mysqlRestartAttempts, 'SQL down on live site. Attempting restart. ' . (3 - $mysqlRestartAttempts) . ' attempts left');
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
