<?php

namespace App\Console\Commands\Supervisors\CoinWorkers;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\CoinReportService;
use App\Services\FutureLiveTrades\LiveTradeLONGFutureServiceEXP1;
use App\Services\FutureLiveTrades\LiveTradeSHORTFutureServiceEXP1;
use App\Services\MailerService;
use Carbon\Carbon;
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

    public $limit;
    public $rsiThreshold;
    public $obvCandles;
    public $obvLimit;
    public $stochLimit;
    public $targetProfit;
    public $market;


    // For trade handler table
    public static $interval;
    public static $leverage = 1;
    public static $openPrice;
    public static $user_id = 1;
    public static $tp = 0.5;
    // This list contains coins that are purposely ignored because they are equal to 1 USDT (Not Fit for trading)
    public static $ignoreList =  [
        "USDCUSDT",
        "TUSDUSDT",
        "BUSDUSDT", // Deprecated but still available in some markets
        "DAIUSDT",
        "FDUSDUSDT",
        "SUSDUSDT",
        "USDPUSDT",
        "LUSDUSDT"
    ];


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
        // Fetch Meta Values
        self::$interval = CommonHelpers::getMetaValue(self::$user_id,'live_trade_worker_interval_future','1m');
        self::$openPrice = CommonHelpers::getMetaValue(self::$user_id,'buy_price_future','5');


        while (true) {
            try {
                $binanceCoins = BinanceApiService::fetchTopUSDTPairsByVolume(1000);

                foreach ($binanceCoins as $binanceCoin) {
                    if (in_array($binanceCoin, self::$ignoreList)) {
                        continue;
                    }
                    $systemCoin = DB::table('coins')->where('symbol', $binanceCoin)->first();


                    if ($systemCoin && $systemCoin->status === 'D') {
                        CommonHelpers::changeCoinStatus($binanceCoin, 'T');
                        self::insertTradeHandlerEntry($binanceCoin, self::$interval, self::$leverage, self::$openPrice, self::$user_id, self::$tp);
                        Log::info("Trade Handlers Generated for: " . $binanceCoin);
                    } else if (!$systemCoin) {
                        CommonHelpers::addNewCoin($binanceCoin);
                        self::insertTradeHandlerEntry($binanceCoin, self::$interval, self::$leverage, self::$openPrice, self::$user_id, self::$tp);
                        Log::info("Trade Handlers Generated for: " . $binanceCoin);
                    } else {
                        continue;
                    }
                }


                $delistedCoins = DB::table('coins')->whereNotIn('symbol', $binanceCoins)->get();

                foreach ($delistedCoins as $delistedCoin) {

                    if (self::removeTradeHandlerEntry($delistedCoin->symbol, self::$user_id)) {
                        CommonHelpers::changeCoinStatus($delistedCoin->symbol, 'D');
                        Log::info("Trade Handlers Removed for: " . $delistedCoin->symbol);
                    } else {
                        Log::error("Skipped Delisting Coin due to open order: " . $binanceCoin);
                    }
                }
                Log::info("Coin List Dumped ");

                // Coin type updates
                Log::info("Starting coin type updates: ");

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
                Log::info("Coin Type updates completed successfully, Putting to sleep for 20 mins... ");
                CommonHelpers::delayMin(20);
            } catch (\Exception $th) {
                Log::error('An error occured: ' . $th);
                Log::error('Retrying in 1 min: ');
            }
            CommonHelpers::delayMin(1);
        }
    }

    public static function removeTradeHandlerEntry($symbol, $user_id)
    {
        $open_order = CommonHelpers::checkOpenOrder($symbol, 'SHORT', 'FUTURE', $user_id);
        if (!$open_order['is_open']) {
            DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $user_id)->delete();
            DB::table('worker_symbols')->where('symbol', $symbol)->delete();
            return true;
        }
        return false;
    }
    public static function insertTradeHandlerEntry($symbol, $interval, $leverage, $openPrice, $user_id, $tp)
    {
        $trade_handlerLong = [
            'market' => 'FUTURE',
            'symbol' => $symbol,
            'interval' => $interval,
            'position' => 'LONG',
            'leverage' => $leverage,
            'buyPrice' => $openPrice,
            'tradeAccount' => $user_id,
            'targetProfit' => $tp,
            'rsiThreshold' => 0,
            'obvLimit' => 0,
            'stochLimit' => 0,
            'isActive' => 1
        ];

        $trade_handlerShort = [
            'market' => 'FUTURE',
            'symbol' => $symbol,
            'interval' => $interval,
            'position' => 'SHORT',
            'leverage' => $leverage,
            'buyPrice' => $openPrice,
            'tradeAccount' => $user_id,
            'targetProfit' => $tp,
            'rsiThreshold' => 0,
            'obvLimit' => 0,
            'stochLimit' => 0,
            'isActive' => 1
        ];

        $idLong = DB::table('trade_handler')->insertGetId($trade_handlerLong);
        $idShort = DB::table('trade_handler')->insertGetId($trade_handlerShort);


        return [
            'LONG' => DB::table('trade_handler')->find($idLong),
            'SHORT' => DB::table('trade_handler')->find($idShort),
        ];
    }
}
