<?php

namespace App\Console\Commands\Supervisors\ReportWorkers;

use App\CommonHelpers;
use App\Services\CoinReportService;
use App\Services\MailerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FutureReportWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-report-worker';
    public $interval;
    public $limit;
    public $rsiThreshold;
    public $obvCandles;
    public $obvLimit;
    public $stochLimit;
    public $targetProfit;
    public $market;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Future Coins Report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {

        MailerService::sendWorkerEmail('future_report_worker');

            $this->market = 'FUTURE';
            $this->interval = CommonHelpers::getSettingsValue('report_worker_interval_future', '1m');
            $this->limit = CommonHelpers::getSettingsValue('report_worker_limit_future', 1000);

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
