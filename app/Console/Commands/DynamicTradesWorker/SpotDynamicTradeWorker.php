<?php

namespace App\Console\Commands\DynamicTradesWorker;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\DynamicTradeService;
use App\Services\MailerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpotCoinDumper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:spot-dynamic-trade-worker';
    public $coins;
    public $minPercentage;
    public $maxPercentage;
    public $quantity;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dynamic Spot Worker';

    /**
     * Execute the console command.
     */
    public function handle()
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
