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


        Log::info("Coin List Dumper started");

        while (true) {
            $this->quantity = CommonHelpers::getSettingsValue('future_coin_worker_quantity', 20);
            try {
                $binanceCoins = BinanceApiService::fetchTopUSDTPairsByVolume(1000);

                foreach ($binanceCoins as $binanceCoin) {
                    $systemCoin = DB::table('coins')->where('symbol', $binanceCoin)->first();

                    if ($systemCoin && $systemCoin->status === 'D') {
                        CommonHelpers::changeCoinStatus($binanceCoin, 'T');
                    } else if (!$systemCoin) {
                        CommonHelpers::addNewCoin($binanceCoin);
                    } else {
                        continue;
                    }
                }


                $delistedCoins = DB::table('coins')->whereNotIn('symbol', $binanceCoins)->get();

                foreach ($delistedCoins as $delistedCoin) {
                    CommonHelpers::changeCoinStatus($delistedCoin->symbol, 'D');
                }

                Log::info("Coin List Dumped");
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMin(20);
        }
    }
}
