<?php

namespace App\Jobs\MarketTrendWorkers;

use App\CommonHelpers;
use App\Services\CoinReportService;
use App\Services\MarketTrendService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class SpotTrendWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $interval;
    public $limit;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->interval = CommonHelpers::getSettingsValue('trend_worker_interval_spot','1m');
        $this->limit = CommonHelpers::getSettingsValue('trend_worker_limit_spot',15);
    }


    public function handle(): void
    {
        
        while (true) {
            MarketTrendService::dumpMarketTrends($this->interval, $this->limit, 'SPOT');
            usleep(10000000); // 300 ms
        }
    }
}
