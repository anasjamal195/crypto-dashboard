<?php

namespace App\Console\Commands;

use App\Jobs\AutoTraderFuture;
use App\Jobs\AutoTraderSpot;
use App\Jobs\CoinReportWorker;
use App\Jobs\CoinWorkers\FutureCoinDumper;
use App\Jobs\CoinWorkers\SpotCoinDumper;
use App\Jobs\IndicatorCandleDumper;
use App\Jobs\MarketTrendAnalyzer;
use App\Jobs\MarketTrendWorkers\FutureTrendWorker;
use App\Jobs\MarketTrendWorkers\SpotTrendWorker;
use App\Jobs\ReportWorkers\FutureReportWorker;
use App\Jobs\ReportWorkers\MainWorker;
use App\Jobs\ReportWorkers\SpotReportWorker;
use App\Jobs\TradeWorker\FutureIdealTradeWorker;
use App\Jobs\TradeWorker\SpotIdealTradeWorker;
use Illuminate\Console\Command;

class DispatchJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command with kill process, flush queues, restart all workers and dispatch all traders all in one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Spot Trade Workers
        SpotCoinDumper::dispatch()->onQueue('spotCoinDumper');
        SpotTrendWorker::dispatch()->onQueue('spotTrendWorker');
        SpotIdealTradeWorker::dispatch()->onQueue('spotIdealTradeWorker');
        SpotReportWorker::dispatch()->onQueue('spotCoinReportWorker');

        // Future Trade Workers
        FutureCoinDumper::dispatch()->onQueue('futureCoinDumper');
        FutureTrendWorker::dispatch()->onQueue('futureTrendWorker');
        FutureIdealTradeWorker::dispatch()->onQueue('futureIdealTradeWorker');
        FutureReportWorker::dispatch()->onQueue('futureCoinReportWorker');
    }
}
