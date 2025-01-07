<?php

namespace App\Jobs\CoinWorkers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureCoinDumper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $coins;
    public $minPercentage;
    public $maxPercentage;
    public $quantity;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->minPercentage = CommonHelpers::getSettingsValue('future_coin_worker_min_percentage', -5);
        $this->maxPercentage = CommonHelpers::getSettingsValue('future_coin_worker_max_percentage', 5);
        $this->quantity = CommonHelpers::getSettingsValue('future_coin_worker_quantity', 20);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        while (true) {
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
