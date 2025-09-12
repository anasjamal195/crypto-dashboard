<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Jobs\TestJob;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\BinanceService;
use App\Services\BlockchainTradingSignalService;
use App\Services\InternalTrader\ReportService;
use App\Services\LiveTrader\BTCUSDT;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        dd("Block");

        $trader = 2;
        $symbol = 'BTCUSDT';

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol=$symbol&timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);
        $queryString .= "&signature=$signature";

        $url = "https://fapi.binance.com/fapi/v1/openOrders?$queryString";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-MBX-APIKEY: $apiKey"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $orders = json_decode($response, true);

        if (!is_array($orders)) {
            return ['error' => true, 'message' => 'Invalid response from Binance', 'raw' => $response];
        }

        dd($orders);

        $results = [];
        foreach ($orders as $order) {
            if (in_array($order['type'], ['STOP_MARKET', 'STOP', 'STOP_LOSS', 'STOP_LOSS_LIMIT', 'TAKE_PROFIT', 'TAKE_PROFIT_MARKET', 'TAKE_PROFIT_LIMIT'])) {
                $results[] = BinanceApiService::cancelOrder($symbol, $trader, $order['orderId']);
            }
        }

        dd( $results);
        dd($info);








        // BTCUSDT::runTrader();


        // $symbol = 'BTCUSDT';
        // $interval = '15m';

        // $topZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('name', 'top_zone')->first()), true);
        // $middleZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('name', 'middle_zone')->first()), true);
        // $bottomZone = json_decode(json_encode(DB::table('sd_zones')->where('symbol', $symbol)->where('name', 'bottom_zone')->first()), true);

        // $currentPrice = BinanceApiService::getCurrentPrice('BTCUSDT', 'FUTURE');
        // $candle = BinanceApiService::getCandleStickData($symbol, $interval, 2, null, 'FUTURE');
        // $candle = $candle[count($candle) - 2];
        // $tp = $currentPrice * (1 + 0.5 / 100);
        // $sl = $currentPrice * (1 - 0.5 / 100);
        // $current_system_time = (int) round(microtime(true) * 1000);

        // $tradeSetupDetails = [
        //     'symbol' => 'BTCUSDT',
        //     'interval' => '15m',
        //     'direction' => 'LONG',
        //     'tp' => $tp,
        //     'sl' => $sl,
        //     'trigger_price' => $currentPrice,
        //     'opening_rule' => 'immidiate_opening',
        //     'zones' => json_encode([
        //         'top_zone' => $topZone,
        //         'middle_zone' => $middleZone,
        //         'bottom_zone' => $bottomZone
        //     ]),
        //     'fvg' => null,
        //     'current_zone' => json_encode($middleZone),
        //     'status' => 'PENDING',
        //     'account_id' => 2,
        //     'candle_timestamp' => $candle['binance_timestamp'],
        //     'timestamp' => $current_system_time,
        //     'strategy_name' => 'TESTING',
        // ];


        // DB::table('trade_setup_details')->insert($tradeSetupDetails);




















        dd("Test");
        // check Top Performing coins

        $reportDetails = [
            [
                'formula' => 'Analysis - Current',
                'timestamp' => null,
                'includeFiltered' => true,
            ],

            // [
            //     'formula' => 'Analysis - Bullish',
            //     'timestamp' => 1746126000000,
            //     'includeFiltered' => true,
            // ],

            // [
            //     'formula' => 'Analysis - Slight Bullish',
            //     'timestamp' => 1744830000000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Flat',
            //     'timestamp' => 1732561200000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Mixed',
            //     'timestamp' => 1744225200000,
            //     'includeFiltered' => true,
            // ],
            // [
            //     'formula' => 'Analysis - Slight Bearish',
            //     'timestamp' => 1745607600000,
            //     'includeFiltered' => true,
            // ],

        ];

        $workerLimit = 5;
        foreach ($reportDetails as $details) {

            $formula = $details['formula'] . ' - Base';
            $timestamp = $details['timestamp'];
            $backtestFormula = ReportService::generateCoinReport($this, $formula, $timestamp);

            // if ($details['includeFiltered']) {
            //     CommonHelpers::filterReportOnWorkerLimit($backtestFormula, $workerLimit);
            // }
        }




        dd("Completed Schedule");
    }
}
