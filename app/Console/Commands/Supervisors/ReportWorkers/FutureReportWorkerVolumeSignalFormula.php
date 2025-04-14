<?php

namespace App\Console\Commands\Supervisors\ReportWorkers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\CoinReportService;
use App\Services\MailerService;
use App\Services\ReportServiceVolumeSignal\LongReportService as ReportServiceVolumeSignalLongReportService;
use App\Services\ReportServiceVolumeSignal\ShortReportService as ReportServiceVolumeSignalShortReportService;
use App\Services\SupportResistanceFakeBreakoutReport\LongReportService;
use App\Services\SupportResistanceFakeBreakoutReport\ShortReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureReportWorkerVolumeSignalFormula extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'app:future-report-worker-volume-signal-formula';
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

        $formula = 'Coin Report - ' . Carbon::now()->format('l, F j, Y h:i A');
        DB::table('coin_reports')->where('market', 'FUTURE')->where('formula', $formula)->delete();

        try {

            ReportServiceVolumeSignalShortReportService::updateCoinReport(
                '5m',
                1000,
                'FUTURE',
                $formula,
                $this,
            );

        } catch (\Exception $e) {
            Log::error($e);
        }
    }
}
