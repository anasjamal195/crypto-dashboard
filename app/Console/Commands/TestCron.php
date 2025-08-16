<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\BlockchainTradingSignalService;
use App\Services\InternalTrader\ReportService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-cron';

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

        // check Top Performing coins

        $reportDetails = [
            [
                'formula' => 'Analysis - Current',
                'timestamp' => null,
                'includeFiltered' => true,
            ],
            // [
            //     'formula' => 'Analysis - Bullish',
            //     'timestamp' => 1746126000000,
            //     'includeFiltered' => true,
            // ],

            // [
            //     'formula' => 'Analysis - Slight Bullish',
            //     'timestamp' => 1744830000000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Flat',
            //     'timestamp' => 1732561200000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Mixed',
            //     'timestamp' => 1744225200000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Slight Bearish',
            //     'timestamp' => 1745607600000,
            //     'includeFiltered' => true,
            // ],





            // [
            //     'formula' => 'Analysis Bearish',
            //     'timestamp' => 1746126000000,
            //     'includeFiltered' => true,
            // ],

        ];

        $workerLimit = 5;
        foreach ($reportDetails as $details) {

            $formula = $details['formula'] . ' - Base';
            $timestamp = $details['timestamp'];
            $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp, null, true);

            if ($details['includeFiltered']) {
                CommonHelpers::filterReportOnWorkerLimit($backtestFormula, $workerLimit);
            }
        }




        dd("Completed Schedule");
    }
}
