<?php

namespace App\Console\Commands\Supervisors;

use App\CommonHelpers;
use App\Services\SupervisorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SafetyWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:safety-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    /**
     * This command monitors every process within the application and acts as a master level kill switch.
     * It observes system processes continuously and ensures a safe termination when required.
     */
    protected $description = 'This command monitors every process within the application and acts as a master level kill switch.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Fixed account for now

        CommonHelpers::addSafetyLog('STARTED_LOGGING');
        $account = 2;
        $triggerThreshold = 5;
        $warningThreshold = 3;
        $checkLong = true;
        $checkShort = true;

        $loggerLoop = true;

        while ($loggerLoop) {

            $lastLog = CommonHelpers::getLatestLog('STARTED_LOGGING');


            if ($checkLong) {
                // Fetch Latest LONG trades
                $longTrades = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_status', 'close')->where('trade_acc', $account);

                if ($lastLog) {
                    $longTrades->where('created_at', '>=', $lastLog->created_at);
                }

                $longTrades = $longTrades->get();
                $lossCount = 0;
                foreach ($longTrades as $trade) {
                    if ($trade->realizedPnl < 0) {
                        $lossCount++;

                        // If 3 consective losses in LONG trades than send warning email
                        if ($lossCount == 3) {

                            CommonHelpers::addSafetyLog('WARNING_LONG', 'Detected three consective LONG trades that closed in losses.');
                        }

                        // If 5 consective losses in LONG trades than send STOP Command and alert email
                        if ($lossCount == 5) {
                            CommonHelpers::killTraderProcess('LONG');
                            $checkLong = false;
                            CommonHelpers::addSafetyLog('KILLED_LONG', 'Detected three consective LONG trades that closed in losses.');
                        }
                    } else {
                        $lossCount = 0;
                    }
                }
            }




            // Check SHORT Trades
            if ($checkShort) {
                // Fetch Latest LONG trades
                $shortTrades = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_status', 'close')->where('trade_acc', $account);

                if ($lastLog) {
                    $shortTrades->where('created_at', '>=', $lastLog->created_at);
                }

                $shortTrades = $shortTrades->get();
                $lossCount = 0;
                foreach ($shortTrades as $trade) {
                    if ($trade->realizedPnl < 0) {
                        $lossCount++;

                        // If 3 consective losses in LONG trades than send warning email
                        if ($lossCount == 3) {

                            CommonHelpers::addSafetyLog('WARNING_SHORT', 'Detected three consective SHORT trades that closed in losses.');
                        }

                        // If 5 consective losses in LONG trades than send STOP Command and alert email
                        if ($lossCount == 5) {
                            CommonHelpers::killTraderProcess('SHORT');
                            $checkShort = false;
                            CommonHelpers::addSafetyLog('KILLED_SHORT', 'Detected three consective SHORT trades that closed in losses.');
                        }
                    } else {
                        $lossCount = 0;
                    }
                }
            }


            if (!$checkLong && !$checkShort) {
                CommonHelpers::addSafetyLog('STOPPED_LOGGING', 'Stopped error Logging');
                SupervisorService::stop('laravel_saftey_worker');
                $loggerLoop = false;
            }
            CommonHelpers::delayMin(5);
        }
    }
}
