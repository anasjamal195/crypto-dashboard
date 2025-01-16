<?php

namespace App\Console\Commands\Supervisors\CoinWorkers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
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
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            usleep(600000000); // 10 mins delay
        }
    }
}
