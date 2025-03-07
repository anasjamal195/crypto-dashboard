<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use App\Services\BinanceApiService;
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
        $candle = BinanceApiService::getCandleStickData("ALGOUSDT", '1m', 300, null, 'FUTURE')[298];
        // Cache::put('BTCUSDT_availability', 0, now()->addMinute());

        dd($candle);
        // TestJob::dispatch("This is queue 3");
        // TestJob::dispatch("This is queue 4");
    }
}
