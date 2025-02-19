<?php

namespace App\Console\Commands\Supervisors\CoinWorkers;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\CoinReportService;
use App\Services\FutureLiveTrades\LiveTradeLONGFutureServiceEXP1;
use App\Services\FutureLiveTrades\LiveTradeSHORTFutureServiceEXP1;
use App\Services\MailerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureCoinDumper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-coin-dumper';
    public $coins;
    public $minPercentage;
    public $maxPercentage;
    public $quantity;
    public $interval;
    public $limit;
    public $rsiThreshold;
    public $obvCandles;
    public $obvLimit;
    public $stochLimit;
    public $targetProfit;
    public $market;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump Future Coins in table';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // Combined Worker for all repeptitive Tasks 


        Log::info("Combined Background Workers Started");
        DB::table('coins')->where('market', 'FUTURE')->delete();
        while (true) {
            $this->minPercentage = CommonHelpers::getSettingsValue('future_coin_worker_min_percentage', -5);
            $this->maxPercentage = CommonHelpers::getSettingsValue('future_coin_worker_max_percentage', 5);
            $this->quantity = CommonHelpers::getSettingsValue('future_coin_worker_quantity', 20);
            try {
                $stableCoins = BinanceApiService::getStableCoins($this->minPercentage, $this->maxPercentage, $this->quantity);
                foreach ($stableCoins as $coin) {
                    if (DB::table('coins')->where('symbol', $coin['symbol'])->where('market', 'FUTURE')->first()) {
                        DB::table('coins')->where('symbol', $coin['symbol'])->where('market', 'FUTURE')->update(
                            [
                                'symbol' => $coin['symbol'],
                                'market' => 'FUTURE',
                            ]
                        );
                    } else {
                        DB::table('coins')->insert(
                            [
                                'symbol' => $coin['symbol'],
                                'market' => 'FUTURE',
                            ]
                        );
                    }
                }

                // // Coin Report Worker
                // $this->interval = CommonHelpers::getSettingsValue('report_worker_interval_future', '1m');
                // $this->limit = CommonHelpers::getSettingsValue('report_worker_limit_future', 1000);
                // CoinReportService::updateCoinReport(
                //     $this->interval,
                //     $this->limit,
                //     $this->market,

                // );


                // Setting Workers
                // foreach (User::all() as $user) {
                //     $interval = CommonHelpers::getMetaValue($user->id, 'live_trade_worker_interval_future', '1m');
                //     LiveTradeLONGFutureServiceEXP1::updateTradeHandler($interval, 'FUTURE', $user->id);
                //     CommonHelpers::delayMS(500);
                // }
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMin(10);
        }
    }
}
