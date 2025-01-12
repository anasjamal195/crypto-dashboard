<?php

namespace App\Jobs\LiveTradeWorker;

use App\CommonHelpers;
use App\Services\IdealTradeService;
use App\Services\LiveTradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpotLiveTradeWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $coins;
    public $interval;
    public $limit;
    public $market;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->interval = CommonHelpers::getSettingsValue('live_trade_worker_interval_spot','1m');
        $this->limit = CommonHelpers::getSettingsValue('live_trade_worker_limit_spot',1000);
        $this->market = 'SPOT';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        
        while (true) {
            
            LiveTradeService::performLiveTrades($this->interval,$this->market);
            usleep(200000);
            
        }
    }
}
