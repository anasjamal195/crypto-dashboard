<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Services\BinanceApiService;
use App\Services\BlockchainTradingSignalService;
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

        $symbol = 'HBARUSDT';

        $supportResistance = [
            'support' => 1,
            'resistance' => 1
        ];



        // $responseOpen = BinanceApiService::openMarketPositionLiveTrader($symbol, 10, 'BUY', 1, 1, 'Test', $supportResistance, 1, false, 0.05, 0.05);
        // dd($responseOpen);


        // $responseUpdate = BinanceApiService::placeTpSlOrders($symbol, 1, 0.2500, 0.19300, 10923632233);
        // BinanceApiService::updateTradeDetails(10923632233, $responseUpdate['takeProfit']['orderId'], $responseUpdate['stopLoss']['orderId'], 'PENDING');
        // dd($responseUpdate);


        // $responseClose = BinanceApiService::closeMarketPositionLiveTrader(10923632233);

        // dd($responseClose);
    }
}
