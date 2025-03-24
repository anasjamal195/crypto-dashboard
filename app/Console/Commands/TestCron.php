<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
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

       
        $symbol = 'BTCUSDT';
        $depth = 1000;
        $orderBookData = BinanceApiService::getOrderBook($symbol, $depth);
        if (!$orderBookData) {
            Log::error("Failed to fetch order book data for {$symbol}");
            return null;
        }

        $orderBookStrategy = new OrderBookStrategy;
        // Analyze the order book
        $analysis = $orderBookStrategy->analyzeOrderBook($symbol, $depth);
        if (!$analysis['success']) {
            Log::error("Failed to analyze order book data for {$symbol}");
            return null;
        }


        dd($analysis);

       
    }
}
