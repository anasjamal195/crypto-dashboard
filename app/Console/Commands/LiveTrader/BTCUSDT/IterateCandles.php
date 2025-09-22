<?php

namespace App\Console\Commands\LiveTrader\BTCUSDT;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\LiveTrader\BTCUSDT;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IterateCandles extends Command
{
    protected $signature = 'app:iterate-candles';
    protected $description = 'Iterate exactly when a new Binance candlestick starts (after boundary is crossed)';

    public function handle()
    {

        $this->info("🚀 IterateCandles started for all intervals in UTC...");
        CommonHelpers::flushZones();

        $this->info("🚀 Flushed all zones...");

        // Backtracing zones
        $data = BinanceApiService::getCandleStickData('BTCUSDT', '15m', 1000, null, 'FUTURE');
        $data1hRaw = BinanceApiService::getCandleStickData('BTCUSDT', '1h', 1000, null, 'FUTURE');
        $data4hRaw = BinanceApiService::getCandleStickData('BTCUSDT', '4h', 1000, null, 'FUTURE');
        foreach ($data as $index => $candle) {
            if ($index >= (count($data) - 1)) {
                break;
            }
            BTCUSDT::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, '15m', 'BTCUSDT');
        }
        $this->info('Previous Zones updated upto: ' . $data[count($data) - 2]['timestampReadable']);


        while (true) {
            $boundary = CommonHelpers::checkCandleBoundaries();

            if ($boundary['1m']) {
                $this->info('1m candle closed at: ' . now('UTC')->toDateTimeString());
            }
            if ($boundary['3m']) {
                $this->info('3m candle closed at: ' . now('UTC')->toDateTimeString());
            }

            if ($boundary['5m']) {
                $this->info('5m candle closed at: ' . now('UTC')->toDateTimeString());
            }

            if ($boundary['15m']) {
                // LOGIC for BTCUSDT handling 
                BTCUSDT::runTrader();
                $this->info('15m candle closed at: ' . now('UTC')->toDateTimeString());
            }

            // if ($boundary['30m']) {
            //     $this->info('30m candle closed at: ' . now('UTC')->toDateTimeString());
            // }

            // if ($boundary['1h']) {
            //     $this->info('1h candle closed at: ' . now('UTC')->toDateTimeString());
            // }

            // if ($boundary['4h']) {
            //     $this->info('4h candle closed at: ' . now('UTC')->toDateTimeString());
            // }

            // if ($boundary['1d']) {
            //     $this->info('1d candle closed at: ' . now('UTC')->toDateTimeString());
            // }
        }
    }
}
