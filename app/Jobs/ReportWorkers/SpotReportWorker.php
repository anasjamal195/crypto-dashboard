<?php

namespace App\Jobs\ReportWorkers;

use App\CommonHelpers;
use App\Services\CoinReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class SpotReportWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $interval;
    public $limit;
    public $rsiThreshold;
    public $obvCandles;
    public $obvLimit;
    public $stochLimit;
    public $targetProfit;
    public $market;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->market = 'SPOT';
        $this->interval = CommonHelpers::getSettingsValue('report_worker_interval_spot', '1m');
        $this->limit = CommonHelpers::getSettingsValue('report_worker_limit_spot', 1000);
      
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        while (true) {
            try {
                CoinReportService::updateCoinReport(
                    $this->interval,
                    $this->limit,
                    $this->market,
                    
                );
            } catch (\Exception $e) {
                Log::error($e);
            }
        }
    }
}
