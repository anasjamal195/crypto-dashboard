<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use App\Services\BinanceApiService;
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
        $supportResistance = MarketTrendService::getCurrentSupportResistanceValue('XRPUSDT', '5m', 'FUTURE', [7]);
        $supportResistanceArr = [
            'support' => $supportResistance[7]['support'],
            'resistance' => $supportResistance[7]['resistance'],
        ];
        // Cache::put('BTCUSDT_availability', 0, now()->addMinute());
        $openOrder = BinanceApiService::openMarketPositionLiveTrader('XRPUSDT',10,'BUY',1,2,'Test formula',$supportResistanceArr);

        dd($openOrder);
        // TestJob::dispatch("This is queue 3");
        // TestJob::dispatch("This is queue 4");
    }
}
