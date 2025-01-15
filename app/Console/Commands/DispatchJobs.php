<?php

namespace App\Console\Commands;

use App\Jobs\AutoTraderFuture;
use App\Jobs\AutoTraderSpot;
use App\Jobs\CoinReportWorker;
use App\Jobs\CoinWorkers\FutureCoinDumper;
use App\Jobs\CoinWorkers\SpotCoinDumper;
use App\Jobs\DynamicTrading\SpotDynamicTradeWorker;
use App\Jobs\IndicatorCandleDumper;
use App\Jobs\MarketTrendAnalyzer;
use App\Jobs\MarketTrendWorkers\FutureTrendWorker;
use App\Jobs\MarketTrendWorkers\SpotTrendWorker;
use App\Jobs\ReportWorkers\FutureReportWorker;
use App\Jobs\ReportWorkers\MainWorker;
use App\Jobs\ReportWorkers\SpotReportWorker;
use App\Jobs\TradeWorker\FutureIdealTradeWorker;
use App\Jobs\TradeWorker\SpotIdealTradeWorker;
use App\Jobs\LiveTradeWorker\SpotLiveTradeWorker;
use App\Jobs\LiveTradeWorker\SpotSettingsWorker;
use Illuminate\Console\Command;

class DispatchJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-jobs
    {--all : Dispatch all workers}
    {--spot : Dispatch only spot workers}
    {--future : Dispatch only future workers}
    {--queue= : Dispatch a specific worker by queue name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will dispatch queued jobs.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->dispatchSpotWorkers();
            $this->dispatchFutureWorkers();
            $this->info("All workers have been dispatched.");
            return;
        }

        if ($this->option('spot')) {
            $this->dispatchSpotWorkers();
            $this->info("Spot workers have been dispatched.");
            return;
        }

        if ($this->option('future')) {
            $this->dispatchFutureWorkers();
            $this->info("Future workers have been dispatched.");
            return;
        }

        if ($queueName = $this->option('queue')) {
            $this->dispatchSpecificWorker($queueName);
            return;
        }

        $this->error("Expected --flag. Available flags are --all, --spot, --future, or --queue=QUEUE_NAME");
        $this->info("Descriptions:");
        $this->info("--all: Dispatch all workers.");
        $this->info("--spot: Dispatch only spot workers.");
        $this->info("--future: Dispatch only future workers.");
        $this->info("--queue=QUEUE_NAME: Dispatch only the specified worker by queue name.");
    }

    protected function dispatchSpotWorkers()
    {
        SpotCoinDumper::dispatch()->onQueue('spotCoinDumper');
        SpotTrendWorker::dispatch()->onQueue('spotTrendWorker');
        SpotIdealTradeWorker::dispatch()->onQueue('spotIdealTradeWorker');
        SpotReportWorker::dispatch()->onQueue('spotCoinReportWorker');
        SpotSettingsWorker::dispatch()->onQueue('spotSettingWorker');
        SpotLiveTradeWorker::dispatch()->onQueue('spotLiveTradeWorker');
        SpotDynamicTradeWorker::dispatch()->onQueue('spotDynamicTradeWorker');
    }

    protected function dispatchFutureWorkers()
    {
        FutureCoinDumper::dispatch()->onQueue('futureCoinDumper');
        FutureTrendWorker::dispatch()->onQueue('futureTrendWorker');
        FutureIdealTradeWorker::dispatch()->onQueue('futureIdealTradeWorker');
        FutureReportWorker::dispatch()->onQueue('futureCoinReportWorker');
    }

    protected function dispatchSpecificWorker($queueName)
    {
        switch ($queueName) {
            case 'spotCoinDumper':
                SpotCoinDumper::dispatch()->onQueue($queueName);
                break;
            case 'spotTrendWorker':
                SpotTrendWorker::dispatch()->onQueue($queueName);
                break;
            case 'spotIdealTradeWorker':
                SpotIdealTradeWorker::dispatch()->onQueue($queueName);
                break;
            case 'spotCoinReportWorker':
                SpotReportWorker::dispatch()->onQueue($queueName);
                break;
            case 'spotLiveTradeWorker':
                SpotLiveTradeWorker::dispatch()->onQueue($queueName);
                break;
            case 'futureCoinDumper':
                FutureCoinDumper::dispatch()->onQueue($queueName);
                break;
            case 'futureTrendWorker':
                FutureTrendWorker::dispatch()->onQueue($queueName);
                break;
            case 'futureIdealTradeWorker':
                FutureIdealTradeWorker::dispatch()->onQueue($queueName);
                break;
            case 'futureCoinReportWorker':
                FutureReportWorker::dispatch()->onQueue($queueName);
                break;
            case 'spotSettingWorker':
                SpotSettingsWorker::dispatch()->onQueue($queueName);
                break;
            case 'spotDynamicTradeWorker':
                SpotDynamicTradeWorker::dispatch()->onQueue($queueName);
                break;
            default:
                $this->error("Invalid queue name provided.");
                return;
        }
        $this->info("Dispatched worker on the '{$queueName}' queue.");
    }
}
