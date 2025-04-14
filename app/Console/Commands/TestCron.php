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

        foreach (DB::table('coins')->where('status', 'T')->whereNull('classification')->get() as $coin) {
            try {
                $details = BinanceApiService::getCoinCategoryDetails(str_replace('USDT', '', $coin->symbol));
                DB::table('coins')->where('id', $coin->id)->update([
                    'classification' => $details['primary_classification'],
                    'is_meme_coin' => $details['classifications']['is_meme_coin'],
                    'is_altcoin' => $details['classifications']['is_altcoin'],
                    'is_nft' => $details['classifications']['is_nft'],
                    'is_defi' => $details['classifications']['is_defi'],
                    'is_metaverse' => $details['classifications']['is_metaverse'],
                    'is_web3' => $details['classifications']['is_web3'],
                ]);
                $this->info('Updated ' . $coin->symbol . ' Category: ' . $details['primary_classification']);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $current = Carbon::now();
                $secondsToWait = 60 - $current->second;
                $this->info('Waiting for ' . $secondsToWait . ' seconds...');
                sleep($secondsToWait);
            }

            sleep(1);
        }
        dd('Done');
    }
}
