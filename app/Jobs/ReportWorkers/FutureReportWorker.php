<?php

namespace App\Jobs\ReportWorkers;

use App\CommonHelpers;
use App\Services\CoinReportService;
use App\Services\ReportService\LongReportService;
use App\Services\ReportService\ShortReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class FutureReportWorker implements ShouldQueue
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
        $this->market = 'FUTURE';
        $this->interval = CommonHelpers::getSettingsValue('report_worker_interval_future', '1m');
        $this->limit = CommonHelpers::getSettingsValue('report_worker_limit_future', 1000);
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::table('coin_reports')->where('market', 'FUTURE')->delete();
        while (true) {
            try {
                ShortReportService::updateCoinReport(
                    '5m',
                    1000,
                    'FUTURE'
                );
                LongReportService::updateCoinReport(
                    '5m',
                    1000,
                    'FUTURE'
                );
            } catch (\Exception $e) {
                Log::error($e);
            }
        }
    }
}
