<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Http\Controllers\BinanceController;
use App\MarketStudyService;
use App\Services\BinanceApiService;
use App\Services\LiveTrader\BNBUSDT;
use App\Services\LiveTrader\BTCUSDT;
use App\Services\LiveTrader\ETHUSDT;
use App\Services\LiveTrader\HBARUSDT;
use App\Services\LiveTrader\SOLUSDT;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestStrategies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-strategies';

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

        $symbols = [
            'BTCUSDT',
            'ETHUSDT'
        ];

        $interval =  '1h';
        $year = 2025;
        $months = [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
        ];


        foreach ($symbols as $symbol) {

            CommonHelpers::console_log("Processing: " . $symbol);

            CommonHelpers::flushZones();
            DB::table('trade_setup_details')->truncate();
            DB::table('opened_trades')->where('symbol', $symbol)->where('interval', $interval)->delete();
            foreach ($months as $month) {
                $limit = null;
                $timestamp = null;
                if ($month && $year) {
                    $params = CommonHelpers::getDataParamsFromMonth($month, $year, $interval);
                    $limit = $params['limit'];
                    $timestamp = $params['timestamp'];
                }
                $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, $limit, $timestamp, 'FUTURE', true);
                BinanceController::runStrategy($data, $symbol, $interval, $timestamp, $limit, $month, $year);
                CommonHelpers::console_log($month . ' Completed.');
            }
        }
    }
}
