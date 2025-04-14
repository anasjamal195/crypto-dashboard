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


        foreach (DB::table('coins')->get() as $coin) {
            $details = BinanceApiService::getCoinCategoryDetails(str_replace('USDT', '', $coin->symbol));


            DB::table('coins')->where('id', $coin->id)->update([
                'classification' => $details['primary_classification'],
            ]);
        }
        dd('Done');
    }
}
