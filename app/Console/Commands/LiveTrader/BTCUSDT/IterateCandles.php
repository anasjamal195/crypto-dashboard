<?php

namespace App\Console\Commands\LiveTrader\BTCUSDT;

use Illuminate\Console\Command;
use Carbon\Carbon;

class IterateCandles extends Command
{
    protected $signature = 'app:iterate-candles {interval=15m}';
    protected $description = 'Iterate exactly when a new Binance candlestick starts (after boundary is crossed)';

    public function handle()
    {
        $interval = $this->argument('interval'); // 1m,5m,15m,30m,1h,4h,1d

        $this->info("🚀 IterateCandles started for interval [$interval] in UTC...");

        while (true) {
            // Wait until just after the next candle boundary
            $nextCandle = $this->getNextCandleTime($interval);
            $sleepSeconds = $nextCandle->diffInSeconds(now('UTC')) + 10; // +1s buffer

            $this->info("⏳ Sleeping {$sleepSeconds}s until next $interval candle at {$nextCandle->toDateTimeString()} UTC");
            sleep($sleepSeconds);

            // ✅ Guaranteed to be AFTER boundary












            $this->info("🔥 New $interval candle at " . now('UTC')->toDateTimeString());

            // --- Your logic here (safe to query Binance latest candle) ---
        }
    }

    private function getNextCandleTime(string $interval): Carbon
    {
        $now = now('UTC')->second(0); // Binance uses UTC
        $nextCandle = null;

        switch ($interval) {
            case '1m':
                $nextCandle = $now->copy()->addMinute()->second(0);
                break;

            case '5m':
            case '15m':
            case '30m':
                $minutes = (int) rtrim($interval, 'm');
                $nextCandle = $now->copy()->minute(
                    floor($now->minute / $minutes) * $minutes
                )->second(0)->addMinutes($minutes);
                break;

            case '1h':
                $nextCandle = $now->copy()->minute(0)->addHour();
                break;

            case '4h':
                // Binance 4h candles start at 01:00, 05:00, 09:00, 13:00, 17:00, 21:00 (UTC)
                $validHours = [1, 5, 9, 13, 17, 21];
                $currentHour = $now->hour;

                $nextHour = null;
                foreach ($validHours as $h) {
                    if ($currentHour < $h || ($currentHour === $h && $now->minute === 0)) {
                        $nextHour = $h;
                        break;
                    }
                }
                if ($nextHour === null) {
                    // roll over to tomorrow 01:00
                    $nextHour = 1;
                    $now = $now->addDay();
                }

                $nextCandle = $now->copy()->hour($nextHour)->minute(0)->second(0);
                break;

            case '1d':
                $nextCandle = $now->copy()->hour(0)->minute(0)->addDay();
                break;

            default:
                throw new \InvalidArgumentException("Unsupported interval: $interval");
        }

        return $nextCandle;
    }
}
