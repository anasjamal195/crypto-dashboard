<?php

namespace App\Console\Commands\Supervisors\CoinWorkers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
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
    protected $signature = 'app:spot-coin-dumper';
    public $coins;
    public $minPercentage;
    public $maxPercentage;
    public $quantity;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump Spot Coins in table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        MailerService::sendWorkerEmail('spot_coin_dumper');
        while (true) {
            $this->minPercentage = CommonHelpers::getSettingsValue('spot_coin_worker_min_percentage', -5);
            $this->maxPercentage = CommonHelpers::getSettingsValue('spot_coin_worker_max_percentage', 5);
            $this->quantity = CommonHelpers::getSettingsValue('spot_coin_worker_quantity', 20);
            try {
                $stableCoins = BinanceApiService::getStableCoins($this->minPercentage, $this->maxPercentage, $this->quantity);

                foreach ($stableCoins as $coin) {
                    if (DB::table('coins')->where('symbol', $coin['symbol'])->where('market', 'SPOT')->first()) {
                        DB::table('coins')->where('symbol', $coin['symbol'])->where('market', 'SPOT')->update(
                            [
                                'symbol' => $coin['symbol'],
                                'market' => 'SPOT',

                            ]
                        );
                    } else {
                        DB::table('coins')->insert(
                            [
                                'symbol' => $coin['symbol'],
                                'market' => 'SPOT',
                            ]
                        );
                    }
                }
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
            }
            CommonHelpers::delayMin(5);
        }
    }
}
