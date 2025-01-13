<?php

namespace App\Jobs\LiveTradeWorker;

use App\CommonHelpers;
use App\Models\User;
use App\Services\IdealTradeService;
use App\Services\LiveTradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Metadata\Uses;

class SpotSettingsWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $coins;
    public $interval;
    public $limit;
    public $market;
    public $priorityQueueLimit;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {

        $this->market = 'SPOT';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {


        while (true) {

            foreach (User::all() as $user) {
                $interval = CommonHelpers::getMetaValue($user->id, 'live_trade_worker_interval_spot', '1m');
                LiveTradeService::updateTradeHandler($interval, $this->market, $user->id);
            }
        }
    }
}
