<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BinanceApiService
{
    protected static $httpClient = null;

    /**
     * Initialize or retrieve the HTTP client.
     */
    protected static function getHttpClient()
    {
        if (self::$httpClient === null) {
            self::$httpClient = Http::withOptions([
                'verify' => !app()->environment('local') // Disable SSL verification in local environment only
            ]);
        }
        return self::$httpClient;
    }

    public static function getStableCoins($minChange, $maxChange, $limit = 0)
    {
        $url = config('binance.api.base_url') . config('binance.endpoints.ticker_24hr');

        $response = self::getHttpClient()->get($url);

        if (!$response->successful()) {
            return [
                'error' => 'Failed to fetch data from Binance',
                'details' => $response->body()
            ];
        }

        $tickers = $response->json();

        $filtered = array_filter($tickers, function ($ticker) use ($minChange, $maxChange) {
            return substr($ticker['symbol'], -4) === "USDT" &&
                floatval($ticker['priceChangePercent']) >= $minChange &&
                floatval($ticker['priceChangePercent']) <= $maxChange;
        });

        if ($limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }



    /**
     * Get candlestick data for a given symbol and interval from Binance API using static method.
     *
     * @param string $symbol
     * @param string $interval
     * @param int $limit
     * @param string $timestamp
     * @param string $trade_type
     * @return array
     * @throws \Exception If the API request fails.
     */
    public static function getCandleStickData($symbol = 'BTCUSDT', $interval = '15m', $limit = 100, $timestamp = '', $trade_type = 'future')
    {
        // Choose the base URL based on the trade type
        $base_url = $trade_type === 'future' ?
            config('binance.api.future_base_url') . config('binance.endpoints.klines') :
            config('binance.api.base_url') . config('binance.endpoints.klines');

        // Prepare parameters for the HTTP request
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit
        ];

        if ($timestamp) {
            $params['startTime'] = $timestamp;
        }

        // Make the HTTP request using Laravel's Http facade with SSL verification disabled conditionally
        $response = Http::withOptions(['verify' => !app()->environment('local')])->get($base_url, $params);

        // Handle the API response
        if (!$response->successful()) {
            throw new \Exception("Failed to fetch data from Binance: {$response->body()}");
        }

        return self::processData($response->json());
    }

    protected static function processData($data)
    {
        $KDJ = self::calculateKDJ($data);

        // Initialize arrays for calculation
        $candlesticks = [];
        $closePrices = [];

        $ema12 = [];
        $ema26 = [];
        $macd = [];
        $signalLine = [];
        $shouldBuy = [];

        $gains = [];
        $losses = [];
        $avgGain = 0;
        $avgLoss = 0;

        $obv = 0;

        // Initialize SAR variables
        $af = 0.02; // Acceleration Factor
        $afStep = 0.02; // Smaller AF increment
        $afMax = 0.2; // Lower max AF

        $trend = 'up'; // Initial trend assumption
        $sar = null;   // Initial SAR
        $ep = null;    // Extreme Point

        $rsiValues = []; // Stores RSI for StochRSI calculation
        $stochRsiValues = []; // Stores calculated StochRSI values



        $candlesticks = [];
        $lengthRsi = 14;
        $smoothK = 3; // Smoothing factor for %K
        $smoothD = 3; // Smoothing factor for %D

        // WR Calculation
        $wrValues = []; // Array to store WR values
        $lookbackPeriod = 14; // Typical lookback period for WR

        $kValues = [50]; // Initial K value
        $dValues = [50]; // Initial D value
        foreach ($data as $index => $candle) {
            // Parse candlestick data
            $timestamp = $candle[0];
            $open = (float) $candle[1];
            $high = (float) $candle[2];
            $low = (float) $candle[3];
            $close = (float) $candle[4];
            $volume = (float) $candle[5];

            $closePrices[] = $close;

            if ($index == 0) {
                // Initialization
                $trend = 'up';
                $sar = $low; // First SAR is the lowest low of the previous trend
                $ep = $high; // Extreme Point for the current trend
                $af = 0.02;  // Acceleration Factor
            } else {
                if ($trend == 'up') {
                    // Calculate SAR for uptrend
                    $sar = $sar + $af * ($ep - $sar);

                    // Prevent SAR from exceeding the lows of the current and previous periods
                    $sar = min($sar, $low, $data[$index - 1][3]);

                    // Update Extreme Point
                    if ($high > $ep) {
                        $ep = $high;
                        $af = min($af + 0.02, 0.2); // Increment AF up to a maximum of 0.2
                    }

                    // Check for trend reversal
                    if ($low < $sar) {
                        $trend = 'down';
                        $sar = $ep; // Reset SAR to the EP of the previous uptrend
                        $ep = $low; // Set new EP for the downtrend
                        $af = 0.02; // Reset AF
                    }
                } else {
                    // Calculate SAR for downtrend
                    $sar = $sar - $af * ($sar - $ep);

                    // Prevent SAR from exceeding the highs of the current and previous periods
                    $sar = max($sar, $high, $data[$index - 1][2]);

                    // Update Extreme Point
                    if ($low < $ep) {
                        $ep = $low;
                        $af = min($af + 0.02, 0.2); // Increment AF up to a maximum of 0.2
                    }

                    // Check for trend reversal
                    if ($high > $sar) {
                        $trend = 'up';
                        $sar = $ep; // Reset SAR to the EP of the previous downtrend
                        $ep = $high; // Set new EP for the uptrend
                        $af = 0.02; // Reset AF
                    }
                }
            }

            // Calculate EMA12 and EMA26
            if ($index == 0) {
                $ema12[] = $close;
                $ema26[] = $close;
            } else {
                $ema12[] = ($close * 2 / (12 + 1)) + ($ema12[$index - 1] * (1 - 2 / (12 + 1)));
                $ema26[] = ($close * 2 / (26 + 1)) + ($ema26[$index - 1] * (1 - 2 / (26 + 1)));
            }

            // Calculate OBV
            if ($index > 0) { // OBV calculation starts from the second candle
                $prevClose = $closePrices[$index - 1];
                if ($close > $prevClose) {
                    $obv += $volume;
                } elseif ($close < $prevClose) {
                    $obv -= $volume;
                }
                // If the close is equal, OBV remains the same
            }

            // Calculate MACD (DIF) and Signal Line (DEA)
            $dif = $ema12[$index] - $ema26[$index];
            $macd[] = $dif;

            if ($index < 9) {
                $signalLine[] = $dif; // Initializing the signal line (DEA)
            } else {
                $signalLine[] = ($dif * 2 / (9 + 1)) + ($signalLine[$index - 1] * (1 - 2 / (9 + 1)));
            }

            // Calculate RSI
            if ($index >= 1) {
                $change = $close - $closePrices[$index - 1];
                if ($change > 0) {
                    $gains[$index] = $change;
                    $losses[$index] = 0;
                } else {
                    $gains[$index] = 0;
                    $losses[$index] = abs($change);
                }
            }

            if ($index == 5) {
                $avgGain = array_sum(array_slice($gains, 1, 6)) / 6;
                $avgLoss = array_sum(array_slice($losses, 1, 6)) / 6;
            } else if ($index > 5) {
                $avgGain = (($avgGain * 5) + $gains[$index]) / 6;
                $avgLoss = (($avgLoss * 5) + $losses[$index]) / 6;
            }

            $rs = $avgLoss == 0 ? 100 : $avgGain / $avgLoss;
            $rsi6 = 100 - (100 / (1 + $rs));
            $rsiValues[] = $rsi6; // Store RSI for StochRSI calculation



            // Stochastic RSI calculation
            $stochRsi = null;
            if (count($rsiValues) >= $lengthRsi) {
                $recentRsi = array_slice($rsiValues, -$lengthRsi);
                $lowestRsi = min($recentRsi);
                $highestRsi = max($recentRsi);

                if ($highestRsi != $lowestRsi) {
                    $stochRsi = ($rsi6 - $lowestRsi) / ($highestRsi - $lowestRsi);
                } else {
                    $stochRsi = 0; // Avoid division by zero
                }
            }

            $stochRsiValues[] = $stochRsi;

            // Add %K values
            if (!is_null($stochRsi)) {
                $kValues[] = $stochRsi * 100; // Scale to percentage
            }

            // Calculate smoothed %K
            $smoothedK = null;
            if (count($kValues) >= $smoothK) {
                $smoothedK = array_sum(array_slice($kValues, -$smoothK)) / $smoothK;
            }

            // Add %D values
            if (!is_null($smoothedK)) {
                $dValues[] = $smoothedK;
            }

            // Calculate smoothed %D
            $smoothedD = null;
            if (count($dValues) >= $smoothD) {
                $smoothedD = array_sum(array_slice($dValues, -$smoothD)) / $smoothD;
            }

            // Add high and low prices to a rolling array
            $highs[] = $high;
            $lows[] = $low;

            // Ensure we only keep the last $lookbackPeriod highs and lows
            if (count($highs) > $lookbackPeriod) {
                array_shift($highs);
                array_shift($lows);
            }

            // Calculate WR if we have enough data
            $wr = null;
            if (count($highs) >= $lookbackPeriod) {
                $highestHigh = max($highs);
                $lowestLow = min($lows);

                if ($highestHigh != $lowestLow) {
                    $wr = (($highestHigh - $close) / ($highestHigh - $lowestLow)) * -100;
                } else {
                    $wr = 0; // Avoid division by zero
                }
            }


            // Calculate Moving Averages
            $ma7 = $index >= 6 ? array_sum(array_slice($closePrices, -7)) / 7 : null;
            $ma14 = $index >= 13 ? array_sum(array_slice($closePrices, -14)) / 14 : null;
            $ma25 = $index >= 24 ? array_sum(array_slice($closePrices, -25)) / 25 : null;
            $ma99 = $index >= 98 ? array_sum(array_slice($closePrices, -99)) / 99 : null;

            // Calculate Percentage Change
            $prevClose = $index > 0 ? $closePrices[$index - 1] : null;
            $percentageChange = $prevClose ? (($close - $prevClose) / $prevClose) * 100 : null;

            // Determine whether to buy
            $buySignal = false;
            if ($index > 9) {
                if ($macd[$index] > $signalLine[$index] && $macd[$index - 1] <= $signalLine[$index - 1]) {  // MACD crosses above Signal Line
                    if ($rsi6 < 70 && $rsi6 > 30) {  // Not overbought or oversold
                        if ($percentageChange > 0.5) {  // Expected profit margin
                            $buySignal = true;
                        }
                    }
                }
            }
            $shouldBuy[] = $buySignal;



            if ($index <= 9) {
                $K = 0;
                $D = 0;
                $J = 0;
            } else {
                $K = $KDJ[$index - 9]['K'];
                $D = $KDJ[$index - 9]['D'];
                $J = $KDJ[$index - 9]['J'];
            }

            // Store candlestick data with all indicators
            $candlesticks[] = [
                'timestamp' => $timestamp,
                'binance_timestamp' => $timestamp,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
                'ma7' => $ma7,
                'ma14' => $ma14,
                'ma25' => $ma25,
                'ma99' => $ma99,
                'rsi6' => $rsi6,
                'per' => $percentageChange,
                'dif' => $dif,
                'dea' => $signalLine[$index],
                'histogram' => $dif - $signalLine[$index],
                'sar' => $sar,
                'should_buy' => false,
                'should_sell' => false,
                'obv' => $obv,
                'stoch_rsi' => $stochRsi,
                'stoch_k' => $smoothedK,
                'stoch_d' => $smoothedD,
                'wr' => $wr,
                'K' => $K,
                'D' => $D,
                'J' => $J,
            ];
        }

        return $candlesticks;
    }

    public static function calculateKDJ($data)
    {
        $candlesticks = [];

        $highs = [];
        $lows = [];
        $closePrices = [];
        $kValues = [50]; // Initial K value
        $dValues = [50]; // Initial D value

        foreach ($data as $index => $candle) {
            if ($index < 9) {
                // Skip the first 9 entries as they're only used for initial calculation setup
                continue;
            }

            $timestamp = $candle[0];
            $open = (float) $candle[1];
            $high = (float) $candle[2];
            $low = (float) $candle[3];
            $close = (float) $candle[4];

            array_push($highs, $high);
            array_push($lows, $low);
            array_push($closePrices, $close);

            if (count($highs) > 9) {
                array_shift($highs);
                array_shift($lows);
                array_shift($closePrices);
            }

            $highestHigh = max($highs);
            $lowestLow = min($lows);

            // Handle division by zero if highest high equals lowest low
            if ($highestHigh == $lowestLow) {
                $rsv = 100; // Can adjust based on how you wish to handle this edge case
            } else {
                $rsv = (($close - $lowestLow) / ($highestHigh - $lowestLow)) * 100;
            }

            $prevK = end($kValues);
            $prevD = end($dValues);
            $k = $prevK * (2 / 3) + $rsv * (1 / 3);
            $d = $prevD * (2 / 3) + $k * (1 / 3);
            $j = 3 * $k - 2 * $d;

            array_push($kValues, $k);
            array_push($dValues, $d);

            $candlesticks[] = [
                'timestamp' => $timestamp,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'K' => $k,
                'D' => $d,
                'J' => $j
            ];
        }

        return $candlesticks;
    }
}
