<?php

namespace App\Console\Commands\Supervisors\MarketTrendWorker;

use App\CommonHelpers;
use App\Services\MarketTrendService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FutureTrendWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-trend-worker';
    public $interval;
    public $limit;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculaet Market Trends in real time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            try {
                $this->interval = CommonHelpers::getSettingsValue('trend_worker_interval_future', '1m');
                $this->limit = CommonHelpers::getSettingsValue('trend_worker_limit_future', 15);
                MarketTrendService::dumpMarketTrends($this->interval, $this->limit, 'FUTURE');
                usleep(10000000); // 300 ms
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
