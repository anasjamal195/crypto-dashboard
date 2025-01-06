<?php

namespace App\Jobs\TradeWorker;

use App\CommonHelpers;
use App\Services\IdealTradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureIdealTradeWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $coins;
    public $interval;
    public $limit;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->interval = CommonHelpers::getSettingsValue('ideal_trade_worker_interval_future','1m');
        $this->limit = CommonHelpers::getSettingsValue('ideal_trade_worker_limit_future',15);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        
        while (true) {
            $this->coins = DB::table('coins')->where('market','FUTURE')->get();

            foreach ($this->coins as $coin) {
                try {
                    IdealTradeService::dumpIdealTrades($coin->symbol, $this->interval, $this->limit, 'FUTURE');
                } catch (\Exception $e) {
                    Log::error('An error occured', $e);
                }
                usleep(500000);
            }
        }
    }
}
