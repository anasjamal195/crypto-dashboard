<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $supportResistance = [
            'support' => 20,
            'resistance' => 30
        ];
        // dd(BinanceApiService::openMarketPositionLiveTrader('XRPUSDT', 7, 'BUY', 2, 1, 'Testing Stop Market', $supportResistance, 0, false, 0.5, 0.5));

        // dd(BinanceApiService::placeOrUpdateStopMarketOrder('XRPUSDT',1,1.5,93210099227));
        dd(BinanceApiService::placeTpSlOrders('XRPUSDT',1,2.3625,2.358,93210099227));


        // dd(BinanceApiService::closeMarketPositionLiveTrader(93197794497));
    }



  
}
