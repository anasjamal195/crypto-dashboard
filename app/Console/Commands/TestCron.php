<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
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

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', 2)->where('symbol', 'PNUTUSDT')->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        $lastOrderClose = $lastOrderClose->created_at;
        $timeDiff =abs( Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));

        dd($timeDiff/60);
        dd(BinanceApiService::getCurrentPrice('BTCUSDT','FUTURE'));
        // TestJob::dispatch("This is queue 3");
        // TestJob::dispatch("This is queue 4");
    }
}
