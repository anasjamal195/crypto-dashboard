<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use Illuminate\Console\Command;

class CandleWickDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:candle-wick-detection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect if the last price entry is an extrema or within the concentrated zone.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = 'IPUSDT';
        $interval = '5m';

        $prices = [];
        $candle = BinanceApiService::getCandleStickData($symbol, $interval, 10, null, 'FUTURE');
        $candle = $candle[count($candle) - 1];
        $candleTimestamp = $candle['binance_timestamp'];
        $centeralValues = [];
        $loop = true;
        while (true) {

            // Check if the current candle has expired (5 minutes passed)
            if ((now()->timestamp * 1000 - $candleTimestamp) / 1000 > 300) {
                $candle = BinanceApiService::getCandleStickData($symbol, $interval, 10, null, 'FUTURE');
                $candle = $candle[count($candle) - 1];
                $candleTimestamp = $candle['binance_timestamp'];
                $prices = []; // Reset prices for the new candle
                continue;
            } else if ((now()->timestamp * 1000 - $candleTimestamp) / 1000 == 300) {
                dd($centeralValues);
            }
            if ((now()->timestamp * 1000 - $candleTimestamp) / 1000 <= 60) {
                $loop = true;
            }
            if ($loop) {
                // Get the current price
                $price = BinanceApiService::getCurrentPrice($symbol, 'FUTURE');
                $prices[] = $price;

                // Calculate median and mode
                $median = $this->calculateMedian($prices);
                $mode = $this->calculateMode($prices);

                // Define a threshold for the concentrated zone (e.g., 20% of the median)
                $threshold = 0.002 * $median;

                // Check if the last entry is an extrema
                $lastEntry = end($prices);
                if (abs($lastEntry - $median) > $threshold || abs($lastEntry - $mode) > $threshold) {
                    $this->info("Extrema detected! Price: " . $lastEntry);
                    if (!in_array(round($lastEntry, 3), $centeralValues))
                        $centeralValues[] = round($lastEntry, 3);
                } else {
                    $this->info("Last entry is within the concentrated zone. Price: " . $lastEntry);
                }

                // Log additional information
                $this->info('Time Past last open: ' . (now()->timestamp * 1000 - $candleTimestamp) / 1000);
                $this->info('Open price: ' . $candle['open']);
                $this->info('Current price: ' . $price);
                $this->info('Median = ' . $median);
                $this->info('Diff Median = ' . ($median - $candle['open']));

                // Delay for 1 second before the next iteration
            } else {
                $this->info('Waiting to begin loop');
            }
            CommonHelpers::delayS(1);
        }
    }

    /**
     * Calculate the median of an array.
     *
     * @param array $arr
     * @return float
     */
    private function calculateMedian($arr)
    {
        if (empty($arr)) {
            return 0;
        }

        sort($arr);
        $count = count($arr);

        if ($count % 2 == 0) {
            $middle1 = $arr[($count / 2) - 1];
            $middle2 = $arr[$count / 2];
            return ($middle1 + $middle2) / 2;
        } else {
            return $arr[floor($count / 2)];
        }
    }

    /**
     * Calculate the mode of an array.
     *
     * @param array $arr
     * @return float
     */
    private function calculateMode($arr)
    {
        if (empty($arr)) {
            return 0;
        }

        $frequency = array_count_values($arr);
        $maxFrequency = max($frequency);
        $modes = array_keys($frequency, $maxFrequency);
        return $modes[0];
    }
}
