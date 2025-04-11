<?php

namespace App\Console\Commands\Supervisors;

use App\CommonHelpers;
use App\Services\SupervisorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

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
        try {
            // Fixed account for now
            $this->info('Starting Safety Worker');
            
            try {
                CommonHelpers::addSafetyLog('STARTED_LOGGING');
                $this->info('Safety logging initialized');
            } catch (Exception $e) {
                $this->error('Failed to add initial safety log: ' . $e->getMessage());
                Log::error('SafetyWorker initialization error: ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                return 1;
            }
            
            $account = 2;
            $triggerThreshold = 5;
            $warningThreshold = 3;
            $checkLong = true;
            $checkShort = true;

            $loggerLoop = true;
            
            while ($loggerLoop) {
                try {
                    $lastLog = CommonHelpers::getLatestLog('STARTED_LOGGING');

                    if ($checkLong) {
                        $this->checkLongPositions($lastLog, $account, $warningThreshold, $triggerThreshold);
                    }

                    if ($checkShort) {
                        $this->checkShortPositions($lastLog, $account, $warningThreshold, $triggerThreshold);
                    }

                    if (!$checkLong && !$checkShort) {
                        $this->info('Both LONG and SHORT monitoring stopped. Shutting down safety worker.');
                        try {
                            CommonHelpers::addSafetyLog('STOPPED_LOGGING', 'Stopped error Logging');
                            SupervisorService::stop('laravel_saftey_worker');
                            $loggerLoop = false;
                        } catch (Exception $e) {
                            $this->error('Failed to stop safety worker: ' . $e->getMessage());
                            Log::error('SafetyWorker shutdown error: ' . $e->getMessage(), [
                                'exception' => $e
                            ]);
                            // Still exit the loop even if stopping the supervisor failed
                            $loggerLoop = false;
                        }
                    }

                    // Add a small try-catch for the delay method to ensure we don't get stuck
                    try {
                        CommonHelpers::delayMin(5);
                    } catch (Exception $e) {
                        $this->warn('Delay method failed: ' . $e->getMessage());
                        Log::warning('SafetyWorker delay error: ' . $e->getMessage());
                        // Fallback to PHP's native sleep function
                        sleep(300);
                    }
                    
                } catch (Exception $e) {
                    $this->error('Error in main monitoring loop: ' . $e->getMessage());
                    Log::error('SafetyWorker monitoring error: ' . $e->getMessage(), [
                        'exception' => $e,
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    
                    // Log this error but continue monitoring
                    try {
                        CommonHelpers::addSafetyLog('MONITORING_ERROR', 'Error in safety monitoring: ' . $e->getMessage());
                    } catch (Exception $logError) {
                        $this->error('Failed to log monitoring error: ' . $logError->getMessage());
                    }
                    
                    // Brief pause before continuing
                    sleep(30);
                }
            }
            
            $this->info('Safety Worker has been stopped');
            return 0;
            
        } catch (Throwable $e) {
            // Catch any potential fatal errors
            $this->error('Fatal error in Safety Worker: ' . $e->getMessage());
            Log::critical('SafetyWorker fatal error: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            try {
                CommonHelpers::addSafetyLog('WORKER_CRASHED', 'Safety worker crashed: ' . $e->getMessage());
            } catch (Exception $logError) {
                // Nothing more we can do here if even logging fails
            }
            
            return 1;
        }
    }
    
    /**
     * Check LONG positions for consecutive losses
     * 
     * @param object|null $lastLog
     * @param int $account
     * @param int $warningThreshold
     * @param int $triggerThreshold
     * @return void
     */
    private function checkLongPositions($lastLog, $account, $warningThreshold, $triggerThreshold)
    {
        try {
            // Fetch Latest LONG trades
            $query = DB::table('live_trades_future_results')
                ->where('position', 'LONG')
                ->where('trade_status', 'close')
                ->where('trade_acc', $account);

            if ($lastLog) {
                $query->where('created_at', '>=', $lastLog->created_at);
            }

            $longTrades = $query->get();

            $lossCount = 0;
            foreach ($longTrades as $trade) {
                try {
                    if ($trade->realizedPnl < 0) {
                        $lossCount++;

                        // If consecutive losses in LONG trades reach warning threshold, send warning email
                        if ($lossCount == $warningThreshold) {
                            $this->warn("LONG position warning threshold reached ($warningThreshold consecutive losses)");
                            CommonHelpers::addSafetyLog('WARNING_LONG', "Detected $warningThreshold consecutive LONG trades that closed in losses.");
                        }

                        // If consecutive losses in LONG trades reach trigger threshold, send STOP Command and alert email
                        if ($lossCount == $triggerThreshold) {
                            $this->error("LONG position kill threshold reached ($triggerThreshold consecutive losses)");
                            CommonHelpers::killTraderProcess('LONG');
                            CommonHelpers::addSafetyLog('KILLED_LONG', "Detected $triggerThreshold consecutive LONG trades that closed in losses.");
                            return false;
                        }
                    } else {
                        $lossCount = 0;
                    }
                } catch (Exception $e) {
                    $this->error('Error processing LONG trade: ' . $e->getMessage());
                    Log::error('Error processing LONG trade in SafetyWorker: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trade_id' => $trade->id ?? 'unknown'
                    ]);
                    // Continue processing other trades
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->error('Error checking LONG positions: ' . $e->getMessage());
            Log::error('Error checking LONG positions in SafetyWorker: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            // Try to log the error but don't stop monitoring
            try {
                CommonHelpers::addSafetyLog('LONG_CHECK_ERROR', 'Error checking LONG positions: ' . $e->getMessage());
            } catch (Exception $logError) {
                // Nothing more we can do here
            }
            
            return true; // Return true to keep monitoring
        }
    }
    
    /**
     * Check SHORT positions for consecutive losses
     * 
     * @param object|null $lastLog
     * @param int $account
     * @param int $warningThreshold
     * @param int $triggerThreshold
     * @return void
     */
    private function checkShortPositions($lastLog, $account, $warningThreshold, $triggerThreshold)
    {
        try {
            // Fetch Latest SHORT trades
            $query = DB::table('live_trades_future_results')
                ->where('position', 'SHORT')
                ->where('trade_status', 'close')
                ->where('trade_acc', $account);

            if ($lastLog) {
                $query->where('created_at', '>=', $lastLog->created_at);
            }

            $shortTrades = $query->get();

            $lossCount = 0;
            foreach ($shortTrades as $trade) {
                try {
                    if ($trade->realizedPnl < 0) {
                        $lossCount++;

                        // If consecutive losses in SHORT trades reach warning threshold, send warning email
                        if ($lossCount == $warningThreshold) {
                            $this->warn("SHORT position warning threshold reached ($warningThreshold consecutive losses)");
                            CommonHelpers::addSafetyLog('WARNING_SHORT', "Detected $warningThreshold consecutive SHORT trades that closed in losses.");
                        }

                        // If consecutive losses in SHORT trades reach trigger threshold, send STOP Command and alert email
                        if ($lossCount == $triggerThreshold) {
                            $this->error("SHORT position kill threshold reached ($triggerThreshold consecutive losses)");
                            CommonHelpers::killTraderProcess('SHORT');
                            CommonHelpers::addSafetyLog('KILLED_SHORT', "Detected $triggerThreshold consecutive SHORT trades that closed in losses.");
                            return false;
                        }
                    } else {
                        $lossCount = 0;
                    }
                } catch (Exception $e) {
                    $this->error('Error processing SHORT trade: ' . $e->getMessage());
                    Log::error('Error processing SHORT trade in SafetyWorker: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trade_id' => $trade->id ?? 'unknown'
                    ]);
                    // Continue processing other trades
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->error('Error checking SHORT positions: ' . $e->getMessage());
            Log::error('Error checking SHORT positions in SafetyWorker: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            // Try to log the error but don't stop monitoring
            try {
                CommonHelpers::addSafetyLog('SHORT_CHECK_ERROR', 'Error checking SHORT positions: ' . $e->getMessage());
            } catch (Exception $logError) {
                // Nothing more we can do here
            }
            
            return true; // Return true to keep monitoring
        }
    }
}