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



        $symbol = 'BTCUSDT';
        $interval =  '1h';
        $limit = null;
        $timestamp = null;


        $month = 'september';
        $year = 2025;


        if ($month && $year) {
            $month = CommonHelpers::$months[$month];
            $calander = CommonHelpers::generateCalendar($year, $month);
            $limit = CommonHelpers::getIndexDiffFromTimestamps(
                $calander['months'][0]['startTime'],
                $calander['months'][0]['endTime'],
                $interval
            ) + 1;
            $timestamp = $calander['months'][0]['startTime'];
        }



        $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, $limit, $timestamp, 'FUTURE', true);

        BinanceController::runStrategy($data, $symbol, $interval, $timestamp, $limit,$month,$year);

        dd("Done");
    }
}
