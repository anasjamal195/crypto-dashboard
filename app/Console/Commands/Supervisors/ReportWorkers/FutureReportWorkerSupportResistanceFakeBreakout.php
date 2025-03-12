<?php

namespace App\Console\Commands\Supervisors\ReportWorkers;

use App\CommonHelpers;
use App\Services\CoinReportService;
use App\Services\MailerService;
use App\Services\SupportResistanceFakeBreakoutReport\LongReportService;
use App\Services\SupportResistanceFakeBreakoutReport\ShortReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureReportWorkerSupportResistanceFakeBreakout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-report-worker-support-resistance-fake-breakout-formula';
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
        DB::table('coin_reports')->where('market', 'FUTURE')->truncate();
     

        // while (true) {
            try {
                // ShortReportService::updateCoinReport(
                //     '5m',
                //     1000,
                //     'FUTURE'
                // );
                LongReportService::updateCoinReport(
                    '5m',
                    1000,
                    'FUTURE'
                );
            } catch (\Exception $e) {
                Log::error($e);
            }
        // }
    }
}
