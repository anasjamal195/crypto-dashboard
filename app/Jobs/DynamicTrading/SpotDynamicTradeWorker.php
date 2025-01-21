<?php

namespace App\Jobs\DynamicTrading;

use App\CommonHelpers;
use App\Services\DynamicTradeService;
use App\Services\IdealTradeService;
use App\Services\LiveTradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpotDynamicTradeWorker implements ShouldQueue
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
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {


        while (true) {
            try {
                DynamicTradeService::checkDynamicTradesSPOT();
                usleep(10000); // 10ms delay
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
        }
    }
}
