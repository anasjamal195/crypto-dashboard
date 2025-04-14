<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceApiService
{
    protected static $httpClient = null;
    protected static $binanceIntervals = [
        '1s'  => 1 / 60,  // 1 second (not commonly used)
        '1m'  => 1,       // 1 minute
        '3m'  => 3,       // 3 minutes
        '5m'  => 5,       // 5 minutes
        '15m' => 15,      // 15 minutes
        '30m' => 30,      // 30 minutes
        '1h'  => 60,      // 1 hour
        '2h'  => 120,     // 2 hours
        '4h'  => 240,     // 4 hours
        '6h'  => 360,     // 6 hours
        '8h'  => 480,     // 8 hours
        '12h' => 720,     // 12 hours
        '1d'  => 1440,    // 1 day
        '3d'  => 4320,    // 3 days
        '1w'  => 10080,   // 1 week
        '1M'  => 43200,   // 1 month (approx 30 days)
    ];

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

        $url = config('binance.cmcApi.base_url') . config('binance.cmcApi.latest_coins');
        $parameters = [
            'start' => '1',
            'limit' => strval($limit),
            'convert' => 'USD'
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: ' . config('binance.cmcApi.api_key')
        ];
        $qs = http_build_query($parameters); // query string encode the parameters
        $request = "{$url}?{$qs}"; // create the request URL


        $curl = curl_init(); // Get cURL resource
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,            // set the request URL
            CURLOPT_HTTPHEADER => $headers,     // set the headers 
            CURLOPT_RETURNTRANSFER => 1,         // ask for raw response instead of bool
            CURLOPT_SSL_VERIFYPEER => false      // disable SSL certificate verification
        ));

        $response = curl_exec($curl); // Send the request, save the response
        $response = json_decode($response, true);
        curl_close($curl); // Close request

        $binanceUSDT = self::fetchBinanceUSDTPairs();
        $allowedPairs = [];
        foreach ($response['data'] as $coin) {
            if (in_array($coin['symbol'], $binanceUSDT)) {
                $allowedPairs[] = $coin['symbol'];
            }
        }
        return array_filter(array_map(function ($value) {
            if ($value != 'USDT')
                return ['symbol' => $value . 'USDT'];
        }, $allowedPairs));
    }
    public static function fetchTopUSDTPairsByVolume($limit = 10)
    {
        // Get trading status info from Binance Futures
        $exchangeInfoUrl = 'https://fapi.binance.com/fapi/v1/exchangeInfo';
        $tickerInfoUrl = 'https://fapi.binance.com/fapi/v1/ticker/24hr';

        // Fetch Futures Exchange Info and Ticker Data
        $exchangeResponse = self::getHttpClient()->get($exchangeInfoUrl);
        $exchangeData = $exchangeResponse->json();

        $tickerResponse = self::getHttpClient()->get($tickerInfoUrl);
        $tickers = $tickerResponse->json();

        // Build a map of symbol => status for Futures market
        $statusMap = [];
        foreach ($exchangeData['symbols'] as $symbol) {
            $statusMap[$symbol['symbol']] = $symbol['status'];
        }

        // Get Spot Market Trading Pairs
        $spotMarketUrl = 'https://api.binance.com/api/v3/exchangeInfo'; // Binance Spot market pairs endpoint
        $spotMarketResponse = json_decode(file_get_contents($spotMarketUrl), true);
        $spotMarketSymbols = [];

        // Filter Spot market symbols with USDT as the quoteAsset
        foreach ($spotMarketResponse['symbols'] as $symbolInfo) {
            if ($symbolInfo['status'] == 'TRADING' && $symbolInfo['quoteAsset'] == 'USDT') {
                $spotMarketSymbols[] = $symbolInfo['symbol'];
            }
        }

        // Filter USDT pairs that are TRADING and available on the Spot market
        $usdtPairs = array_filter($tickers, function ($ticker) use ($statusMap, $spotMarketSymbols) {
            return str_ends_with($ticker['symbol'], 'USDT') &&
                isset($statusMap[$ticker['symbol']]) &&
                $statusMap[$ticker['symbol']] === 'TRADING' &&
                in_array($ticker['symbol'], $spotMarketSymbols); // Check if it exists on the Spot market
        });

        // Sort by quoteVolume
        usort($usdtPairs, function ($a, $b) {
            return (float)$b['quoteVolume'] <=> (float)$a['quoteVolume'];
        });

        // Get top N base assets based on volume
        $topBaseAssets = array_map(function ($ticker) {
            return $ticker['symbol'];
        }, array_slice($usdtPairs, 0, $limit));

        return $topBaseAssets;
    }


    public static function fetchBinanceUSDTPairs()
    {
        $url = config('binance.api.future_base_url') . config('binance.endpoints.exchange_info');

        $binanceResponse = self::getHttpClient()->get($url);
        $data = $binanceResponse->json();
        $usdtPairs = [];
        foreach ($data['symbols'] as $symbol) {
            if ($symbol['status'] === 'TRADING' && strpos($symbol['symbol'], 'USDT') !== false) {
                $usdtPairs[] = $symbol['baseAsset'];
            }
        }
        return $usdtPairs;
    }
    /**
     * Fetch maintenance margin rate for a specific symbol from Binance.
     *
     * @param string $symbol The trading pair symbol, like 'BTCUSDT'.
     * @return float|null Maintenance margin rate, or null if not found.
     */
    public static function getMaintenanceMarginRate($symbol)
    {
        $url = config('binance.api.future_base_url') . config('binance.endpoints.exchange_info');
        $response = self::getHttpClient()->get($url);
        if ($response->successful()) {
            $data = $response->json();
            foreach ($data['symbols'] as $item) {
                if ($item['symbol'] === $symbol) {

                    return $item['maintMarginPercent']; // Convert percentage to decimal
                }
            }
        }

        return null; // Return null if the symbol is not found or the API call fails
    }

    /**
     * Calculate the liquidation price for a given entry price, leverage, and margin rate.
     *
     * @param float $entryPrice The price at which the trade is entered.
     * @param float $leverage The leverage used for the trade.
     * @param float $maintenanceMarginRate The maintenance margin rate.
     * @return float
     */
    public static function calculateLiquidationPrice($symbol, $entryPrice, $leverage, $positionType = 'long')
    {
        $maintenanceMarginRate = self::getMaintenanceMarginRate($symbol) / 100; // As a decimal
        $initialMargin = 1 / $leverage; // Initial margin as a fraction of leverage

        if ($positionType === 'long') {
            $liquidationPrice = $entryPrice * (1 - (1 / $leverage) + ($maintenanceMarginRate / $leverage));
        } else {
            $liquidationPrice = $entryPrice * (1 + (1 / $leverage) + ($maintenanceMarginRate / $leverage));
        }

        return $liquidationPrice;
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
    public static function getCandleStickData($symbol = 'BTCUSDT', $interval = '15m', $limit = 100, $timestamp = '', $market = 'SPOT', $processed = true)
    {

        $cacheKey = "binance_api_weight_klines";
        $balancerServerSequence = [
            'https://digitalfitnesshub.shop/wp-includes/restful-api/',            // Removed due to SSL error
            'https://xnfts.shop/load_balancer/index.php',                         // Chain Server II     
            // 'https://pompsplace.cc/load_balancer/',                            // Unavailable due to binance.com restrictions on its location
            // 'https://egeniuscare.com/load_balancer/',                          // Unavailable due to binance.com restrictions on its location
            // 'https://rx4less.shop/load_balancer/' ,                            // Unavailable due to binance.com restrictions on its location                                 
        ];
        static $serverUrlKey = 0;

        $response = null; // Initialize a null response

        // Retrieve stored weight usage from cache
        $usedWeight = Cache::get($cacheKey, 0);
        $remainingWeight = 1200 - $usedWeight;

        // Prepare parameters
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
            'startTime' => $timestamp,
        ];

        // Check if the remaining weight is too low to make another request to next available server
        if (intval($remainingWeight) < 100) {
            // Log::warning("Approaching rate limit for Binance API ($usedWeight/1200). Switching server...");

            // Increment balancer index and loop if out of bounds
            $params['balancerServerSequence'] = $balancerServerSequence;
            $params['nextServer'] = $serverUrlKey;
            $response = Http::withOptions(['verify' => !app()->environment('local')])->asForm()->post($balancerServerSequence[$serverUrlKey], $params);
            $response->getHeaders();
        } else {
            // Choose the base URL based on the trade type
            // Log::info("Using Master Server: ($usedWeight/1200). Retaining...");

            $base_url = $market === 'FUTURE' ?
                config('binance.api.future_base_url') . config('binance.endpoints.klines') :
                config('binance.api.base_url') . config('binance.endpoints.klines');

            // Make the HTTP request
            $response = Http::withOptions(['verify' => !app()->environment('local')])->get($base_url, $params);

            $headers = $response->getHeaders();

            if (isset($headers["x-mbx-used-weight-1m"][0])) {
                $usedWeight = (int) $headers["x-mbx-used-weight-1m"][0];
                Cache::put($cacheKey, $usedWeight, now()->addMinute());
            }
        }

        // Handle the API response
        if (!$response->successful()) {
            $openSymbols = DB::table('live_trades_future_results')->where('trade_status', 'open')->where('symbol', $symbol)->first();
            if (!$openSymbols) {
                DB::table('coins')->where('symbol', $symbol)->delete();
            } else {
                Log::info('Error Delete Invalid Coin, Order Open for symbol: ' . $symbol);
            }
            Log::error('Error Fetching Coin data: ' . $symbol . ' ' . json_encode($response->json()));
            // dd($response->json());
        }


        if (!$response->json()) {
            Log::error('Error Fetching Coin data: ' . $symbol . ' ' . $response->body());
        }

        // Update API weight usage in cache

        if ($processed)
            return self::processData($response->json(), $market);
        else
            return $response->json();
    }


    protected static function processData($data, $market = 'SPOT')
    {
        // Calculate KDJ (predefined function)
        $KDJ = self::calculateKDJ($data);

        // Initialize base data arrays
        $closePrices = [];
        $highPrices = [];
        $lowPrices = [];
        $volumes = [];
        $candlesticks = [];

        // Initialize technical indicator arrays
        $ema12 = [];
        $ema26 = [];
        $macd = [];
        $signalLine = [];
        $gains = [];
        $losses = [];
        $avgGain = 0;
        $avgLoss = 0;
        $obv = 0;
        $rsiValues = [];
        $stochRsiValues = [];
        $kValues = [50]; // Initial K value
        $dValues = [50]; // Initial D value
        $shouldBuy = [];

        // Initialize parameters for indicators
        $lengthRsi = 14;
        $smoothK = 3;
        $smoothD = 3;
        $lookbackPeriod = 14;
        $bbPeriod = 20;
        $bbDeviation = 2;

        // SAR parameters
        $af = 0.02;      // Acceleration Factor
        $afStep = 0.02;  // AF increment
        $afMax = 0.2;    // Max AF
        $trend = 'up';   // Initial trend assumption
        $sar = null;     // Initial SAR
        $ep = null;      // Extreme Point

        // ADX parameters
        $adxPeriod = 14;
        $trueRanges = [];
        $dmPlus = [];
        $dmMinus = [];
        $smoothedTR = [];
        $smoothedDMPlus = [];
        $smoothedDMMinus = [];
        $diPlus = [];
        $diMinus = [];
        $dx = [];
        $adxValues = [];

        // Process each candle
        foreach ($data as $index => $candle) {
            // Extract basic candle data
            $timestamp = $candle[0];
            $open = (float) $candle[1];
            $high = (float) $candle[2];
            $low = (float) $candle[3];
            $close = (float) $candle[4];
            $volume = (float) $candle[5];

            // Store values for future calculations
            $closePrices[] = $close;
            $highPrices[] = $high;
            $lowPrices[] = $low;
            $volumes[] = $volume;

            $timestampReadable = \Carbon\Carbon::createFromTimestampMs($timestamp)
                ->setTimezone('Asia/Karachi')
                ->toDateTimeString();

            // Calculate ADX components
            if ($index > 0) {
                $prevHigh = $highPrices[$index - 1];
                $prevLow = $lowPrices[$index - 1];
                $prevClose = $closePrices[$index - 1];

                // Calculate True Range
                $tr = max(
                    abs($high - $low),
                    abs($high - $prevClose),
                    abs($low - $prevClose)
                );
                $trueRanges[] = $tr;

                // Calculate Directional Movement
                $upMove = $high - $prevHigh;
                $downMove = $prevLow - $low;

                // +DM and -DM
                if ($upMove > $downMove && $upMove > 0) {
                    $dmPlus[] = $upMove;
                } else {
                    $dmPlus[] = 0;
                }

                if ($downMove > $upMove && $downMove > 0) {
                    $dmMinus[] = $downMove;
                } else {
                    $dmMinus[] = 0;
                }

                // Calculate smoothed values after collecting enough data
                if ($index == $adxPeriod) {
                    // First average for the period
                    $smoothedTR[] = array_sum($trueRanges) / $adxPeriod;
                    $smoothedDMPlus[] = array_sum($dmPlus) / $adxPeriod;
                    $smoothedDMMinus[] = array_sum($dmMinus) / $adxPeriod;
                } elseif ($index > $adxPeriod) {
                    // Wilder's smoothing method
                    $lastTR = end($smoothedTR);
                    $smoothedTR[] = $lastTR - ($lastTR / $adxPeriod) + $tr;

                    $lastDMPlus = end($smoothedDMPlus);
                    $smoothedDMPlus[] = $lastDMPlus - ($lastDMPlus / $adxPeriod) + end($dmPlus);

                    $lastDMMinus = end($smoothedDMMinus);
                    $smoothedDMMinus[] = $lastDMMinus - ($lastDMMinus / $adxPeriod) + end($dmMinus);

                    // Calculate +DI and -DI
                    $lastSmoothedTR = end($smoothedTR);
                    $diPlus[] = 100 * (end($smoothedDMPlus) / $lastSmoothedTR);
                    $diMinus[] = 100 * (end($smoothedDMMinus) / $lastSmoothedTR);

                    // Calculate DX
                    $diDiff = abs(end($diPlus) - end($diMinus));
                    $diSum = end($diPlus) + end($diMinus);
                    $dx[] = 100 * ($diDiff / max(0.000001, $diSum)); // Avoid division by zero

                    // Calculate ADX
                    if (count($dx) >= $adxPeriod) {
                        if (count($dx) == $adxPeriod) {
                            // First ADX is simple average of DX
                            $adxValues[] = array_sum(array_slice($dx, -$adxPeriod)) / $adxPeriod;
                        } else {
                            // Subsequent ADX uses smoothing
                            $adxValues[] = ((end($adxValues) * ($adxPeriod - 1)) + end($dx)) / $adxPeriod;
                        }
                    }
                }
            } else {
                // First candle - initialize values
                $trueRanges[] = $high - $low; // Initial TR is just the range
                $dmPlus[] = 0;
                $dmMinus[] = 0;
            }

            // Calculate Parabolic SAR
            if ($index == 0) {
                $trend = 'up';
                $sar = $low;  // Initial SAR
                $ep = $high;  // Initial Extreme Point
                $af = 0.02;   // Initial Acceleration Factor
            } else {
                $prevLow = $lowPrices[$index - 1];
                $prevHigh = $highPrices[$index - 1];

                // SAR calculation based on trend
                if ($trend == 'up') {
                    // In uptrend
                    if ($high > $ep) {
                        $ep = $high;
                        $af = min($af + $afStep, $afMax);
                    }
                    $sar = min($sar + $af * ($ep - $sar), $low, $prevLow);

                    // Check for trend reversal
                    if ($low < $sar) {
                        $trend = 'down';
                        $sar = $ep;
                        $ep = $low;
                        $af = 0.02;
                    }
                } else {
                    // In downtrend
                    if ($low < $ep) {
                        $ep = $low;
                        $af = min($af + $afStep, $afMax);
                    }
                    $sar = max($sar - $af * ($sar - $ep), $high, $prevHigh);

                    // Check for trend reversal
                    if ($high > $sar) {
                        $trend = 'up';
                        $sar = $ep;
                        $ep = $high;
                        $af = 0.02;
                    }
                }
            }

            // Calculate EMA12 and EMA26
            if ($index == 0) {
                $ema12[] = $close;
                $ema26[] = $close;
            } else {
                $ema12[] = self::calculateEMA($close, $ema12[$index - 1], 12);
                $ema26[] = self::calculateEMA($close, $ema26[$index - 1], 26);
            }

            // Calculate OBV (On Balance Volume)
            if ($index > 0) {
                $prevClose = $closePrices[$index - 1];
                if ($close > $prevClose) {
                    $obv += $volume;
                } elseif ($close < $prevClose) {
                    $obv -= $volume;
                }
                // If close equals previous close, OBV remains unchanged
            }

            // Calculate MACD and Signal Line
            $dif = $ema12[$index] - $ema26[$index];
            $macd[] = $dif;

            if ($index < 9) {
                $signalLine[] = $dif; // Initialize signal line
            } else {
                $signalLine[] = self::calculateEMA($dif, $signalLine[$index - 1], 9);
            }

            // Calculate RSI
            if ($index >= 1) {
                $change = $close - $closePrices[$index - 1];
                $gains[$index] = $change > 0 ? $change : 0;
                $losses[$index] = $change < 0 ? abs($change) : 0;

                if ($index == 5) {
                    $avgGain = array_sum(array_slice($gains, 1, 6)) / 6;
                    $avgLoss = array_sum(array_slice($losses, 1, 6)) / 6;
                } elseif ($index > 5) {
                    $avgGain = (($avgGain * 5) + $gains[$index]) / 6;
                    $avgLoss = (($avgLoss * 5) + $losses[$index]) / 6;
                }

                $rs = $avgLoss == 0 ? 100 : $avgGain / $avgLoss;
                $rsi6 = 100 - (100 / (1 + $rs));
                $rsiValues[] = $rsi6;
            } else {
                $rsi6 = null;
            }

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

            // Williams %R calculation
            $wr = null;
            if ($index >= $lookbackPeriod - 1) {
                $periodHighs = array_slice($highPrices, -$lookbackPeriod);
                $periodLows = array_slice($lowPrices, -$lookbackPeriod);
                $highestHigh = max($periodHighs);
                $lowestLow = min($periodLows);

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

            // Calculate Bollinger Bands
            $bbMiddle = null;
            $bbUpper = null;
            $bbLower = null;

            if ($index >= ($bbPeriod - 1)) {
                $recentPrices = array_slice($closePrices, -$bbPeriod);
                $bbMiddle = array_sum($recentPrices) / $bbPeriod;

                // Calculate standard deviation
                $sumSquaredDiff = 0;
                foreach ($recentPrices as $price) {
                    $sumSquaredDiff += pow($price - $bbMiddle, 2);
                }
                $standardDeviation = sqrt($sumSquaredDiff / $bbPeriod);

                // Calculate upper and lower bands
                $bbUpper = $bbMiddle + ($bbDeviation * $standardDeviation);
                $bbLower = $bbMiddle - ($bbDeviation * $standardDeviation);
            }

            // Calculate Percentage Change
            $percentageChange = null;
            if ($index > 0) {
                $prevClose = $closePrices[$index - 1];
                $percentageChange = (($close - $prevClose) / $prevClose) * 100;
            }

            // Determine buy signal
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

            // Get KDJ values
            if ($index <= 9) {
                $K = 0;
                $D = 0;
                $J = 0;
            } else {
                $K = $KDJ[$index - 9]['K'];
                $D = $KDJ[$index - 9]['D'];
                $J = $KDJ[$index - 9]['J'];
            }

            // Calculate OBV levels
            $previousObvHigh = 0;
            $previousObvLow = 0;
            if ($index > 15) {
                $previousObvHigh = $candlesticks[$index - 15]['obv'];
                $previousObvLow = $previousObvHigh;

                for ($i = $index - 15; $i < $index; $i++) {
                    if ($previousObvHigh < $candlesticks[$i]['obv']) {
                        $previousObvHigh = $candlesticks[$i]['obv'];
                    }
                    if ($previousObvLow > $candlesticks[$i]['obv']) {
                        $previousObvLow = $candlesticks[$i]['obv'];
                    }
                }
            }

            // Calculate Volume MAs
            $ma5_volume = $index >= 4 ? array_sum(array_slice($volumes, -5)) / 5 : null;
            $ma10_volume = $index >= 9 ? array_sum(array_slice($volumes, -10)) / 10 : null;

            // AVL Calculation
            $avl = ($high + $low) / 2;

            // Get current ADX values
            $currentDiPlus = count($diPlus) ? end($diPlus) : null;
            $currentDiMinus = count($diMinus) ? end($diMinus) : null;
            $currentADX = count($adxValues) ? end($adxValues) : null;

            // Store candlestick data with all indicators
            $candlesticks[] = [
                'timestamp' => $timestamp,
                'timestampReadable' => $timestampReadable,
                'market' => $market,
                'binance_timestamp' => $timestamp,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
                'volumeMA5' => $ma5_volume,
                'volumeMA10' => $ma10_volume,
                'avl' => $avl,
                'ma7' => $ma7,
                'ma14' => $ma14,
                'ma25' => $ma25,
                'ma99' => $ma99,
                'bb_middle' => $bbMiddle,
                'bb_upper' => $bbUpper,
                'bb_lower' => $bbLower,
                'rsi6' => $rsi6,
                'per' => $percentageChange,
                'dif' => $dif,
                'dea' => $index > 0 ? $signalLine[$index] : null,
                'histogram' => $index > 0 ? $dif - $signalLine[$index] : null,
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
                'previousObvHigh' => $previousObvHigh,
                'previousObvLow' => $previousObvLow,

                // ADX components

                'adx' => $currentADX,
                // PDI (Red)
                'di_plus' => $currentDiPlus,
                // MDI (Blue)
                'di_minus' => $currentDiMinus,
            ];
        }

        return $candlesticks;
    }

    public static function getCoinCategoryDetails($symbol)
    {



        $url = config('binance.cmcApi.base_url') . config('binance.cmcApi.info');

        // Set up the headers
        $headers = [
            'X-CMC_PRO_API_KEY: ' . config('binance.cmcApi.api_key'),
            'Accept: application/json'
        ];

        // Set up the query parameters
        $queryParams = http_build_query([
            'symbol' => strtoupper($symbol) // Ensure symbol is uppercase
        ]);

        // Initialize cURL session
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url . '?' . $queryParams,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        // Execute the request
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        // Handle any errors
        if ($error) {
            error_log("CoinMarketCap API Error: " . $error);
            return null;
        }

        // Parse the response
        $data = json_decode($response, true);

        // Check if the request was successful
        if (isset($data['status']) && $data['status']['error_code'] === 0) {
            // Extract the coin data
            if (isset($data['data'][strtoupper($symbol)])) {
                $coinData = $data['data'][strtoupper($symbol)];

                // Get category and tags
                $category = $coinData['category'] ?? null;
                $tags = $coinData['tags'] ?? [];

                // Determine if it's a meme coin, alt coin, or other category
                $isMeme = self::checkIfMemeCoin($coinData);
                $isAltcoin = self::checkIfAltcoin($symbol, $coinData);
                $isNFT = self::checkIfNFT($coinData);
                $isDeFi = self::checkIfDeFi($coinData);
                $isMetaverse = self::checkIfMetaverse($coinData);
                $isWeb3 = self::checkIfWeb3($coinData);


                $classifications = [
                    'is_meme_coin' => $isMeme,
                    'is_altcoin' => $isAltcoin,
                    'is_nft' => $isNFT,
                    'is_defi' => $isDeFi,
                    'is_metaverse' => $isMetaverse,
                    'is_web3' => $isWeb3,
                ];


                $priorityMap = [
                    'is_meme_coin'   => 'Meme Coin',
                    'is_defi'        => 'DeFi',
                    'is_nft'         => 'NFT',
                    'is_metaverse'   => 'Metaverse',
                    'is_web3'        => 'Web3',
                    'is_altcoin'     => 'Altcoin',
                ];

                $primaryClassification = null;
                foreach ($priorityMap as $key => $label) {
                    if (!empty($classifications[$key])) {
                        $primaryClassification =  $label;
                    }
                }



                // Optional fallback to primary_classification if nothing is matched
                if (!$primaryClassification) {
                    $primaryClassification =  'Unclassified';
                }

                // Extract and return relevant category information
                return [
                    'symbol' => $symbol,
                    'name' => $coinData['name'] ?? null,
                    'category' => $category,
                    'tags' => $tags,
                    'classifications' => $classifications,
                    'primary_classification' => self::determinePrimaryClassification($isMeme, $isAltcoin, $isNFT, $isDeFi, $isMetaverse, $isWeb3),
                    'platform' => $coinData['platform'] ?? null,
                    'description' => $coinData['description'] ?? null,
                    'logo' => $coinData['logo'] ?? null,
                    'date_added' => $coinData['date_added'] ?? null,
                    'urls' => $coinData['urls'] ?? null
                ];
            }
        } else {
            // Log the error
            $errorMessage = isset($data['status']['error_message'])
                ? $data['status']['error_message']
                : 'Unknown error';
            error_log("CoinMarketCap API Error: " . $errorMessage);
        }

        return null;
    }

    public static function checkIfMemeCoin($coinData)
    {
        $category = strtolower($coinData['category'] ?? '');
        $tags = array_map('strtolower', $coinData['tags'] ?? []);
        $name = strtolower($coinData['name'] ?? '');
        $description = strtolower($coinData['description'] ?? '');

        // Check for explicit meme category or tags
        if ($category === 'meme' || in_array('meme', $tags) || in_array('meme coin', $tags)) {
            return true;
        }

        // List of keywords commonly associated with meme coins
        $memeKeywords = ['meme', 'dog', 'shiba', 'doge', 'pepe', 'elon', 'moon', 'safe', 'shib', 'inu'];

        // Check name for meme keywords
        foreach ($memeKeywords as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return true;
            }
        }

        // Check description for meme indicators
        $memeDescriptionKeywords = ['meme', 'community driven', 'joke', 'fun', 'viral', 'community coin'];
        foreach ($memeDescriptionKeywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a coin is an altcoin
     * 
     * @param string $symbol Coin symbol
     * @param array $coinData Coin data from CoinMarketCap
     * @return bool True if it's an altcoin
     */
    public static function checkIfAltcoin($symbol, $coinData)
    {
        // Bitcoin is not an altcoin, everything else is
        if (strtoupper($symbol) === 'BTC') {
            return false;
        }

        // All others are considered altcoins
        return true;
    }

    /**
     * Check if a coin is NFT-related
     * 
     * @param array $coinData Coin data from CoinMarketCap
     * @return bool True if it's NFT-related
     */
    public static function checkIfNFT($coinData)
    {
        $category = strtolower($coinData['category'] ?? '');
        $tags = array_map('strtolower', $coinData['tags'] ?? []);
        $description = strtolower($coinData['description'] ?? '');

        // Check for explicit NFT category or tags
        if ($category === 'nft' || in_array('nft', $tags) || in_array('collectibles', $tags)) {
            return true;
        }

        // Check description for NFT indicators
        $nftKeywords = ['non-fungible token', 'nft', 'collectible', 'digital art', 'digital collectible'];
        foreach ($nftKeywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a coin is DeFi-related
     * 
     * @param array $coinData Coin data from CoinMarketCap
     * @return bool True if it's DeFi-related
     */
    public static function checkIfDeFi($coinData)
    {
        $category = strtolower($coinData['category'] ?? '');
        $tags = array_map('strtolower', $coinData['tags'] ?? []);
        $description = strtolower($coinData['description'] ?? '');

        // Check for explicit DeFi category or tags
        if ($category === 'defi' || in_array('defi', $tags) || in_array('decentralized finance', $tags)) {
            return true;
        }

        // Check description for DeFi indicators
        $defiKeywords = ['decentralized finance', 'defi', 'yield farming', 'lending', 'borrowing', 'decentralized exchange', 'dex', 'amm', 'liquidity', 'staking'];
        foreach ($defiKeywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a coin is Metaverse-related
     * 
     * @param array $coinData Coin data from CoinMarketCap
     * @return bool True if it's Metaverse-related
     */
    public static function checkIfMetaverse($coinData)
    {
        $category = strtolower($coinData['category'] ?? '');
        $tags = array_map('strtolower', $coinData['tags'] ?? []);
        $description = strtolower($coinData['description'] ?? '');

        // Check for explicit Metaverse category or tags
        if ($category === 'metaverse' || in_array('metaverse', $tags) || in_array('virtual world', $tags)) {
            return true;
        }

        // Check description for Metaverse indicators
        $metaverseKeywords = ['metaverse', 'virtual world', 'virtual reality', 'vr', 'augmented reality', 'ar', 'digital land'];
        foreach ($metaverseKeywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a coin is Web3-related
     * 
     * @param array $coinData Coin data from CoinMarketCap
     * @return bool True if it's Web3-related
     */
    public static function checkIfWeb3($coinData)
    {
        $category = strtolower($coinData['category'] ?? '');
        $tags = array_map('strtolower', $coinData['tags'] ?? []);
        $description = strtolower($coinData['description'] ?? '');

        // Check for explicit Web3 category or tags
        if ($category === 'web3' || in_array('web3', $tags) || in_array('web 3.0', $tags)) {
            return true;
        }

        // Check description for Web3 indicators
        $web3Keywords = ['web3', 'web 3.0', 'decentralized web', 'decentralized internet', 'decentralized application', 'dapp'];
        foreach ($web3Keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the primary classification of the coin
     * 
     * @param bool $isMeme Is it a meme coin
     * @param bool $isAltcoin Is it an altcoin
     * @param bool $isNFT Is it NFT-related
     * @param bool $isDeFi Is it DeFi-related
     * @param bool $isMetaverse Is it Metaverse-related
     * @param bool $isWeb3 Is it Web3-related
     * @return string Primary classification
     */
    public static function determinePrimaryClassification($isMeme, $isAltcoin, $isNFT, $isDeFi, $isMetaverse, $isWeb3)
    {
        if ($isMeme) {
            return "MEME";
        } else if ($isNFT) {
            return "NFT";
        } else if ($isDeFi) {
            return "DEFI";
        } else if ($isMetaverse) {
            return "METAVERSE";
        } else if ($isWeb3) {
            return "WEB3";
        } else if ($isAltcoin) {
            return "ALTCOIN";
        } else {
            return "OTHER";
        }
    }
    /**
     * Helper method for calculating Exponential Moving Average
     * 
     * @param float $price Current price
     * @param float $prevEma Previous period's EMA
     * @param int $period EMA period
     * @return float Calculated EMA
     */
    private static function calculateEMA($price, $prevEma, $period)
    {
        $multiplier = 2 / ($period + 1);
        return ($price * $multiplier) + ($prevEma * (1 - $multiplier));
    }
    public static function estimateRSIAtPercentage($symbol, $interval, $timestampNow)
    {
        $data = BinanceApiService::getCandleStickDataPast($symbol, $interval, 100, $timestampNow, 'FUTURE');

        $intervalInMs = self::$binanceIntervals[$interval] * 60000;
        $candle = $data[count($data) - 1];
        $previousCandle = $data[count($data) - 2];
        // Convert full RSI to RS
        $rsiFull = $candle['rsi6'];
        $open = $candle['open'];
        $close = $candle['close'];





        // Estimating the % of candle formation till current $binanceTimestamp
        $candleStartTime = $candle['binance_timestamp'];
        $candleEndTime = $candle['binance_timestamp'] + $intervalInMs;
        $userTime = $timestampNow;

        // Compute RSI at 50% of candle formation
        $nPercent = (($userTime - $candleStartTime) / ($candleEndTime - $candleStartTime)) * 100;


        // Estimate avg gain/loss if previous candle is available
        $previousCandle = null; // Replace with actual previous candle if available
        if ($previousCandle) {
            $previousClose = $previousCandle['close'];
            $gain = max(0, $close - $previousClose);
            $loss = max(0, $previousClose - $close);
            $avgGainPrev = ($gain + $previousCandle['rsi6']) / 2;
            $avgLossPrev = ($loss + $previousCandle['rsi6']) / 2;
        } else {
            $avgGainPrev = abs($close - $open) * 0.5; // Approximation
            $avgLossPrev = abs($close - $open) * 0.5;
        }


        $rsFull = (100 / (100 - $rsiFull)) - 1;

        // Estimate close price at n% of candle formation
        $closeAtNPercent = $open + (($close - $open) * ($nPercent / 100));

        // Calculate gain/loss at n%
        if ($closeAtNPercent > $open) {
            $gainN = $closeAtNPercent - $open;
            $lossN = 0;
        } else {
            $lossN = $open - $closeAtNPercent;
            $gainN = 0;
        }

        // Adjust RS using estimated gain/loss
        $rsN = $rsFull * (($gainN + $avgGainPrev) / ($lossN + $avgLossPrev));

        // Calculate RSI at n%
        $rsiN = 100 - (100 / (1 + $rsN));

        return $rsiN;
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


    // Order Book Details
    public static function getOrderBook(string $symbol, int $limit = 100, $apiPointerUrl = null): ?array
    {

        $url = config('binance.api.future_base_url') . config('binance.endpoints.depth');
        if ($apiPointerUrl) {
            $url = $apiPointerUrl;
        }
        try {
            $params = [
                'symbol' => $symbol,
                'limit' => $limit,
            ];
            $response = self::getHttpClient()->get($url, $params);
            $headers = $response->getHeaders();
            if (isset($headers["x-mbx-used-weight-1m"][0]) && !$apiPointerUrl) {
                $usedWeight = (int) $headers["x-mbx-used-weight-1m"][0];
                if ($usedWeight >= 1100) {
                    $resetTime = 60 - now()->format('s');
                    sleep($resetTime);
                }
            }

            if ($response->successful() || isset($response->json()['error'])) {
                return $response->json();
            }

            Log::error('Binance API Error: ' . $response->body());
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching order book: ' . $e->getMessage());
            return null;
        }
    }

    // Misc Candle data functions for internal trader

    public static function getCandleStickDataPast($symbol = 'BTCUSDT', $interval = '15m', $limit = 100, $timestamp = '', $market = 'SPOT')
    {


        $intervalInMins = self::$binanceIntervals[$interval];

        $revisedTimestamp = $timestamp - ($intervalInMins * ($limit) * 60000) +  1000;

        $data = self::getCandleStickData($symbol, $interval, $limit, $revisedTimestamp, $market);
        // dd($data);
        return $data;
    }

    // Live Trades Functions 
    public static function getCurrentPrice($symbol, $market = 'SPOT')
    {
        $params = [
            'symbol' => $symbol,
        ];
        $url = '';
        if ($market == 'FUTURE')
            $url = config('binance.api.future_base_url') . config('binance.endpoints.ticker_price');
        else
            $url = config('binance.api.base_url') . config('binance.endpoints.ticker_price');

        $ticker = self::getHttpClient()->get($url, $params);

        Log::info('Price Response for ' . $symbol . ': ' . json_encode(isset($ticker['price']) ? $ticker['price'] : '0'));


        return isset($ticker['price']) ? $ticker['price'] : '0';
    }

    private static function getTotalCommission($apiResponse)
    {
        $totalCommission = 0;
        $commissionAsset = '';

        // Check if fills array exists
        if (isset($apiResponse['fills']) && is_array($apiResponse['fills'])) {
            foreach ($apiResponse['fills'] as $fill) {
                // Sum up the commission
                $totalCommission += (float) $fill['commission'];

                // Get the commission asset (assuming it's the same for all fills)
                if (empty($commissionAsset)) {
                    $commissionAsset = $fill['commissionAsset'];
                }
            }
        }

        return [
            'totalCommission' => $totalCommission,
            'commissionAsset' => $commissionAsset,
            'commissionAssetUSDT' => $commissionAsset != 'USDT' ? self::getCurrentPrice($commissionAsset . 'USDT') : $totalCommission,
        ];
    }

    public static function fetchAvailableQuantity($symbol, $trader, $market = 'SPOT')
    {

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');

        // Get server time from Binance API to sync up the request
        $serverTime = json_decode(file_get_contents($base_url . config('binance.endpoints.server_time')), true);
        $serverTimestamp = $serverTime['serverTime'];

        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        // Adjust timestamp if necessary
        if ($timestamp - $serverTimestamp > $recvWindow) {
            $timestamp = $serverTimestamp + $recvWindow;
        }

        // Prepare query string for signature
        $queryString = http_build_query([
            'timestamp' => $timestamp,
            'recvWindow' => $recvWindow
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        // Construct the request URL
        $url = $base_url . config('binance.endpoints.account_info') . '?' . $queryString;

        // Make the API request to Binance
        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->get($url);

        $response = $response->json();
        // print_r($response);exit;
        // dd($response);
        if (isset($response['balances'])) {
            $balance = collect($response['balances'])->where('asset', str_replace('USDT', '', $symbol))->first();
            return [
                'asset' => $symbol,
                'free' => $balance['free'] ? floatval($balance['free']) : 0, // Available balance
                'locked' => $balance['locked'] ? floatval($balance['locked']) : 0 // Balance in orders
            ];
        } else {
            // Log or handle the error appropriately
            Log::error('Failed to fetch balance for ' . $symbol . ' for trader ' . $trader . ': ' . json_encode($response));
            return null;
        }
    }

    public static function placeBuyOrder($symbol, $interval, $amount,  $trader, $market = 'SPOT')
    {

        $current_price = self::getCurrentPrice($symbol);
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');

        // Get server time from Binance API
        $serverTime = json_decode(file_get_contents($base_url . config('binance.endpoints.server_time')), true);
        $serverTimestamp = $serverTime['serverTime'];

        // Calculate timestamp and recvWindow
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        // Adjust timestamp if necessary
        if ($timestamp - $serverTimestamp > $recvWindow) {
            $timestamp = $serverTimestamp + $recvWindow;
        }

        // Fetch exchange information to get LOT_SIZE filter
        $exchangeInfo = json_decode(file_get_contents($base_url . config('binance.endpoints.exchange_info') . "?symbol=$symbol"), true);
        $filters = $exchangeInfo['symbols'][0]['filters'];

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Calculate and adjust the quantity
        $quantity = $amount / $current_price;
        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];

        // Ensure quantity is within the allowed limits
        if ($quantity < $lotSize['minQty'] || $quantity > $lotSize['maxQty']) {
            throw new Exception("Quantity $quantity is outside the allowed LOT_SIZE limits for symbol $symbol");
        }

        $url = $base_url . config('binance.endpoints.order');

        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();



        $fee_details = self::getTotalCommission($response);
        if (isset($response['code'])) {
            Log::info('Trader ' . $trader . ': Buy response' . json_encode($response));
            return $response;
        }

        // $coinReportsLiveId = DB::table('coin_reports_live')->insertGetId([
        //     'symbol' => $response['symbol'],
        //     'interval' => $interval,
        //     'market' => $market,
        //     'status' => 'active', // Assuming you have a status or similar field
        //     'created_at' => Carbon::now('Asia/Karachi'),
        //     'updated_at' => Carbon::now('Asia/Karachi')
        // ]);
        $data =  [
            'symbol' => $response['symbol'],
            'amount' => $amount,
            'interval' => $interval,
            'market' => $market,
            'orderId' => $response['orderId'],
            'status' => $response['status'],
            'type' => $response['type'],
            'side' => $response['side'],
            'price' => $current_price,
            'trade_status' => 'open',
            'trade_acc' => $trader,
            'qty' => $quantity,
            // 'coin_reports_live_id' => $coinReportsLiveId,
            'commission' => $fee_details['totalCommission'],
            'commission_asset' => $fee_details['commissionAsset'],
            'commissionUSDT' => $fee_details['commissionAssetUSDT'],
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('orders')->insert(
            $data
        );


        MailerService::sendEmail($data);
        return $data;
    }

    public static function placeSellOrder($buyOrderId)
    {

        $buy_order = DB::table('orders')->where('orderId', $buyOrderId)->first();
        $trader = $buy_order->trade_acc;
        $quantity = $buy_order->qty;
        $symbol = $buy_order->symbol;
        $market = $buy_order->market;
        $amount = $buy_order->amount;
        $interval = $buy_order->interval;
        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');



        // Get server time from Binance API
        $serverTime = json_decode(file_get_contents($base_url . config('binance.endpoints.server_time')), true);
        $serverTimestamp = $serverTime['serverTime'];

        // Calculate timestamp and recvWindow
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        // Adjust timestamp if necessary
        if ($timestamp - $serverTimestamp > $recvWindow) {
            $timestamp = $serverTimestamp + $recvWindow;
        }

        // Fetch exchange information to get LOT_SIZE filter
        $exchangeInfo = json_decode(file_get_contents($base_url . config('binance.endpoints.exchange_info') . "?symbol=$symbol"), true);
        $filters = $exchangeInfo['symbols'][0]['filters'];

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Calculate and adjust the quantity
        $quantity = $amount / $current_price;
        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];

        // Ensure quantity is within the allowed limits
        if ($quantity < $lotSize['minQty'] || $quantity > $lotSize['maxQty']) {
            throw new Exception("Quantity $quantity is outside the allowed LOT_SIZE limits for symbol $symbol");
        }

        $url = $base_url . config('binance.endpoints.order');

        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();
        Log::info('Trader ' . $trader . ': Sell response' . json_encode($response));


        if ($response['code'] == -2010) {
            Log::info('Trader ' . $trader . ' Symbol: ' . $symbol . ' Insufficient Balance' . ' Buy Order: ' . $buy_order->orderId);
            // DB::table('orders')
            //     ->where('id', $buy_order->id)
            //     ->update(
            //         [
            //             'pair_id' => -1,
            //             'trade_status' => 'close',
            //         ]
            //     );
            return false;
        }

        // return $response;
        $fee_details = self::getTotalCommission($response);
        $data =  [
            'symbol' => $response['symbol'],
            'amount' => $amount,
            'interval' => $interval,
            'market' => $market,
            'orderId' => $response['orderId'],
            'status' => $response['status'],
            'type' => $response['type'],
            'side' => $response['side'],
            'price' => $current_price,
            'trade_status' => 'close',
            'trade_acc' => $trader,
            'qty' => $quantity,
            'commission' => $fee_details['totalCommission'],
            'commission_asset' => $fee_details['commissionAsset'],
            'commissionUSDT' => $fee_details['commissionAssetUSDT'],
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('orders')->insert(
            $data
        );

        DB::table('orders')
            ->where('orderId', $buy_order->orderId)
            ->where('trade_acc', $data['trade_acc'])
            ->update(
                [
                    'pair_id' => $data['orderId'],
                    'trade_status' => 'close',
                ]
            );
        DB::table('orders')
            ->where('orderId', $data['orderId'])
            ->where('trade_acc', $data['trade_acc'])
            ->update(
                [
                    'pair_id' => $buy_order->orderId,
                    'trade_status' => 'close',
                ]
            );

        $data['pair_id'] = $buy_order->orderId;
        MailerService::sendEmail($data);

        return $data;
    }




    public static function placeDynamicBuyOrderSpot($symbol, $amount,  $trader, $trade = null)
    {

        $current_price = self::getCurrentPrice($symbol);
        $user = User::find($trader);
        $market = 'SPOT';
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');

        // Get server time from Binance API
        $serverTime = json_decode(file_get_contents($base_url . config('binance.endpoints.server_time')), true);
        $serverTimestamp = $serverTime['serverTime'];

        // Calculate timestamp and recvWindow
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        // Adjust timestamp if necessary
        if ($timestamp - $serverTimestamp > $recvWindow) {
            $timestamp = $serverTimestamp + $recvWindow;
        }

        // Fetch exchange information to get LOT_SIZE filter
        $exchangeInfo = json_decode(file_get_contents($base_url . config('binance.endpoints.exchange_info') . "?symbol=$symbol"), true);
        $filters = $exchangeInfo['symbols'][0]['filters'];

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Calculate and adjust the quantity
        $quantity = $amount / $current_price;
        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];

        // Ensure quantity is within the allowed limits
        if ($quantity < $lotSize['minQty'] || $quantity > $lotSize['maxQty']) {
            throw new Exception("Quantity $quantity is outside the allowed LOT_SIZE limits for symbol $symbol");
        }

        $url = $base_url . config('binance.endpoints.order');

        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();



        $fee_details = self::getTotalCommission($response);
        if (isset($response['code'])) {
            Log::info('Trader ' . $trader . ': Buy response' . json_encode($response));
            return $response;
        }


        $data =  [
            'symbol' => $response['symbol'],
            'orderId' => $response['orderId'],
            'tradeId' => $trade ? $trade->id : null,
            'side' => $response['side'],
            'amount' => $amount,
            'qty' => $quantity,
            'status' => $response['status'],
            'price' => $current_price,
            'trade_acc' => $trader,
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('dynamic_trades_spot_results')->insert(
            $data
        );


        MailerService::sendSpotTradeDynamicEmail($data);
        return $data;
    }

    public static function placeDynamicSellOrderSpot($symbol, $quantity,  $trader, $trade)
    {

        $market = 'SPOT';

        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');



        // Get server time from Binance API
        $serverTime = json_decode(file_get_contents($base_url . config('binance.endpoints.server_time')), true);
        $serverTimestamp = $serverTime['serverTime'];

        // Calculate timestamp and recvWindow
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = 5000;

        // Adjust timestamp if necessary
        if ($timestamp - $serverTimestamp > $recvWindow) {
            $timestamp = $serverTimestamp + $recvWindow;
        }

        // Fetch exchange information to get LOT_SIZE filter
        $exchangeInfo = json_decode(file_get_contents($base_url . config('binance.endpoints.exchange_info') . "?symbol=$symbol"), true);
        $filters = $exchangeInfo['symbols'][0]['filters'];

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Calculate and adjust the quantity

        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];

        // Ensure quantity is within the allowed limits
        if ($quantity < $lotSize['minQty'] || $quantity > $lotSize['maxQty']) {
            throw new Exception("Quantity $quantity is outside the allowed LOT_SIZE limits for symbol $symbol");
        }

        $url = $base_url . config('binance.endpoints.order');

        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();


        if (!isset($response['symbol'])) {
            Log::info('Trader ' . $trader . ': Sell response' . json_encode($response));
        }
        $data =  [
            'symbol' => $response['symbol'],
            'orderId' => $response['orderId'],
            'tradeId' => $trade ? $trade->id : null,
            'side' => $response['side'],
            'amount' => $quantity * $current_price,
            'qty' => $quantity,
            'status' => $response['status'],
            'price' => $current_price,
            'trade_acc' => $trader,
            'created_at' => Carbon::now('Asia/Karachi'),
        ];


        DB::table('dynamic_trades_spot_results')->insert(
            $data
        );

        MailerService::sendSpotTradeDynamicEmail($data);


        return $data;
    }


    // Future Api's
    public static function openMarketPositionLiveTrader($symbol, $tradeAmount, $position = 'BUY', $leverage, $trader, $formula = '', $supportResistance, $turnoverPoint, $isDummy = false, $stopLossPercentage = 0.5, $targetProfit = 0.5)
    {

        $market = 'FUTURE';
        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');


        // Step 1: Set leverage

        $leverageUrl = $base_url . config('binance.endpoints.leverage');

        $leverageData = [
            "symbol" => $symbol,
            "leverage" => $leverage,
            "timestamp" => round(microtime(true) * 1000),
        ];

        $leverageQuery = http_build_query($leverageData);
        $leverageSignature = hash_hmac('sha256', $leverageQuery, $apiSecret);
        $leverageQuery .= "&signature=" . $leverageSignature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $leverageUrl . "?" . $leverageQuery);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $leverageResponse = curl_exec($ch);
        curl_close($ch);
        $leverageResponse = json_decode($leverageResponse, true);
        if (isset($leverageResponse['code']) && $leverageResponse['code'] < 0) {
            throw new Exception("Failed to set leverage: " . $leverageResponse['msg']);
        }
        // Step 2: Fetch trading rules
        $exchangeInfoUrl =  $base_url . config('binance.endpoints.exchange_info');
        $exchangeInfo = json_decode(file_get_contents($exchangeInfoUrl . "?symbol=$symbol"), true);

        foreach ($exchangeInfo['symbols'] as $excInfo) {
            if ($excInfo['symbol'] == $symbol) {
                $filters = $excInfo['filters'];
                break;
            }
        }

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Step 3: Calculate position quantity
        $positionSize = $tradeAmount * $leverage; // Total position size with leverage
        $quantity = $positionSize / $current_price;      // Contract quantity based on the price

        // Adjust quantity to match LOT_SIZE step size
        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];


        $url = $base_url . config('binance.endpoints.order');

        $timestamp =  round(microtime(true) * 1000);




        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;


        // For Dummy Trades
        if ($isDummy) {

            $orderId = random_int(100000, 999999);
            $exists = DB::table('live_trades_future_results')->where('orderId', $orderId)->first();
            // Calculate liquidation price
            $entryPrice = $current_price; // Assuming trade executed at provided price
            $accountMargin = $tradeAmount; // User's margin
            $liquidationPrice = 0;
            $stopLoss = 0;

            if ($position === 'BUY') {
                $stopLoss = $current_price * (1 - 0.5 / 100);
            } else if ($position === 'SELL') {
                $stopLoss = $current_price * (1 + 0.5 / 100);
            }


            while ($exists) {
                $orderId = random_int(100000, 999999);
                $exists = DB::table('live_trades_future_results')->where('orderId', $orderId)->first();
            }
            $data =  [
                'orderId' => $orderId,
                'symbol' => $symbol,
                'side' => $position,
                'amount' => $tradeAmount,
                'type' => 'open',
                'position' => $position === 'BUY' ? 'LONG' : 'SHORT',
                'qty' => $quantity,
                'leverage' => $leverage,
                'stopLoss' => $stopLoss,
                'stopLossReductionPrecentage' => 0.1,
                'price' => $current_price,
                'trade_status' => 'open',
                'trade_acc' => $trader,
                'targetProfit' => 0.4,
                'formula' => 'Dummy: ' . $formula,
                'isDummy' => true,
                'liqPrice' => $liquidationPrice,
                'created_at' => Carbon::now('Asia/Karachi'),
            ];

            DB::table('live_trades_future_results')->insert(
                $data
            );
            $data['support'] = $supportResistance['support'];
            $data['resistance'] = $supportResistance['resistance'];
            if ($position === 'BUY') {
                $data['supportResistanceChange'] = (($current_price - $data['resistance']) / $data['resistance']) * 100;
            } else if ($position === 'SELL') {
                $data['supportResistanceChange'] = (($current_price - $data['support']) / $data['support']) * 100;
            }
            $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position'] . ' ' . $formula . ' :: Account ' . User::find($data['trade_acc'])->name . ' Amount: ' . $data['amount'] . '$';
            MailerService::sendFutureTradeDynamicEmail($data);

            return $data;
        }

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();



        if (isset($response['code']) && $response['code'] < 0) {
            throw new Exception("Order failed: " . $response['msg']);
        }

        // Calculate liquidation price
        $entryPrice = $current_price; // Assuming trade executed at provided price
        $accountMargin = $tradeAmount; // User's margin
        $liquidationPrice = 0;
        $stopLoss = 0;

        if ($position === 'BUY') {
            $liquidationPrice = $entryPrice - ($accountMargin / ($quantity * $leverage));
            $stopLoss = $current_price * (1 - $stopLossPercentage / 100) < $liquidationPrice ? $liquidationPrice * (1 + 0.3 / 100) : $current_price * (1 - $stopLossPercentage / 100);
        } else if ($position === 'SELL') {
            $liquidationPrice = $entryPrice + ($accountMargin / ($quantity * $leverage));
            $stopLoss = $current_price * (1 + $stopLossPercentage / 100) > $liquidationPrice ? $liquidationPrice * (1 - 0.3 / 100) : $current_price * (1 + $stopLossPercentage / 100);
        }


        $data =  [
            'orderId' => $response['orderId'],
            'symbol' => $response['symbol'],
            'side' => $response['side'],
            'amount' => $tradeAmount,
            'type' => 'open',
            'position' => $position === 'BUY' ? 'LONG' : 'SHORT',
            'qty' => $quantity,
            'leverage' => $leverage,
            'stopLoss' => $stopLoss,
            'stopLossReductionPrecentage' => 0.1,
            'price' => $current_price,
            'trade_status' => 'open',
            'trade_acc' => $trader,
            'targetProfit' => $targetProfit,
            'formula' => $formula,
            'turnoverPoint' => $turnoverPoint,
            'liqPrice' => $liquidationPrice,
            'currentSupport' => $supportResistance['support'],
            'currentResistance' => $supportResistance['resistance'],
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('live_trades_future_results')->insert(
            $data
        );
        $data['support'] = $supportResistance['support'];
        $data['resistance'] = $supportResistance['resistance'];
        if ($position === 'BUY') {
            $data['supportResistanceChange'] = (($current_price - $data['resistance']) / $data['resistance']) * 100;
        } else if ($position === 'SELL') {
            $data['supportResistanceChange'] = (($current_price - $data['support']) / $data['support']) * 100;
        }
        $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position'] . ' ' . $formula . ' ' . $symbol . ' :: Account ' . User::find($data['trade_acc'])->name . ' Amount: ' . $data['amount'] . '$';
        MailerService::sendFutureTradeDynamicEmail($data);

        return $data;
    }

    public static function closeMarketPositionLiveTrader($openOrderId)
    {


        $openOrder = DB::table('live_trades_future_results')->where('orderId', $openOrderId)->first();
        $market = 'FUTURE';
        $position = $openOrder->side == 'BUY' ? 'SELL' : 'BUY';
        $symbol = $openOrder->symbol;
        $trader = $openOrder->trade_acc;
        $quantity = $openOrder->qty;

        $positionDetails = self::getPositionDetails($symbol, $trader);
        if ($openOrder->trade_status === 'close' || !$positionDetails) {
            return false;
        }

        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');
        $url = $base_url . config('binance.endpoints.order');

        $timestamp =  round(microtime(true) * 1000);
        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;


        if ($openOrder->isDummy) {

            $orderId = random_int(100000, 999999);
            $exists = DB::table('live_trades_future_results')->where('orderId', $orderId)->first();
            while ($exists) {
                $orderId = random_int(100000, 999999);
                $exists = DB::table('live_trades_future_results')->where('orderId', $orderId)->first();
            }
            $currentProfit = 0;
            if ($position === 'BUY') {
                $currentProfit = (($openOrder->price - $current_price) / $openOrder->price) * 100;
            } else {
                $currentProfit = (($current_price - $openOrder->price) / $openOrder->price) * 100;
            }
            $data =  [
                'orderId' => $orderId,
                'pairId' => $openOrder->pairId,
                'symbol' => $symbol,
                'side' => $position,
                'amount' => $openOrder->amount,
                'qty' => $quantity,
                'position' => $position === 'BUY' ? 'SHORT' : 'LONG',
                'type' => 'close',
                'trade_status' => 'close',
                'leverage' => 0,
                'price' => $current_price,
                'currentProfit' => $currentProfit,
                'isDummy' => $openOrder->isDummy,
                'trade_acc' => $trader,
                'liqPrice' => 0,
                'created_at' => Carbon::now('Asia/Karachi'),
            ];

            DB::table('live_trades_future_results')->insert(
                $data
            );
            DB::table('live_trades_future_results')->where('orderId', $openOrderId)->update([
                'trade_status' => 'close',
                'pairId' => $orderId,

            ]);
            $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position'] . ' ' . $openOrder->formula  . ' :: Account ' . User::find($data['trade_acc'])->name . ' ' . round($data['currentProfit'], 2) . '% ' . ($data['currentProfit'] >= 0 ? '(Profit)' : '(Loss)') . ' Amount: ' . $data['amount'] . '$';

            MailerService::sendFutureTradeDynamicEmail($data);
            return $data;
        }
        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();


        if (isset($response['code']) && $response['code'] < 0) {
            throw new Exception("Order failed: " . $response['msg']);
        }

        $currentProfit = 0;
        if ($position === 'BUY') {
            $currentProfit = (($openOrder->price - $current_price) / $openOrder->price) * 100;
        } else {
            $currentProfit = (($current_price - $openOrder->price) / $openOrder->price) * 100;
        }
        // Fee Details




        $data =  [
            'orderId' => $response['orderId'],
            'pairId' => $openOrder->pairId,
            'symbol' => $response['symbol'],
            'side' => $response['side'],
            'amount' => $openOrder->amount,
            'qty' => $quantity,
            'position' => $position === 'BUY' ? 'SHORT' : 'LONG',
            'type' => 'close',
            'trade_status' => 'close',
            'leverage' => 0,
            'price' => $current_price,
            'currentProfit' => $currentProfit,
            'trade_acc' => $trader,
            'liqPrice' => 0,

            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('live_trades_future_results')->insert(
            $data
        );



        $feeUsdt = 0;
        $realizedPnl = 0;

        // For close order
        $feeDetails = self::getFeeDetails($response['orderId']);

        foreach ($feeDetails as $fee) {
            $feeUsdt += floatval($fee['commission']);
            $realizedPnl += floatval($fee['realizedPnl']);
        }

        // For close order
        $feeDetails = self::getFeeDetails($openOrderId);

        foreach ($feeDetails as $fee) {
            $feeUsdt += floatval($fee['commission']);
            $realizedPnl += floatval($fee['realizedPnl']);
        }

        DB::table('live_trades_future_results')->where('orderId', $response['orderId'])->update([
            'trade_status' => 'close',
            'feeUsdt' => $feeUsdt,
            'realizedPnl' => $realizedPnl,

        ]);


        DB::table('live_trades_future_results')->where('orderId', $openOrderId)->update([
            'trade_status' => 'close',
            'pairId' => $response['orderId'],
            'feeUsdt' => $feeUsdt,
            'realizedPnl' => $realizedPnl,

        ]);
        $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position']  . ' ' . $openOrder->formula  . ' ' . $symbol . ' :: Account ' . User::find($data['trade_acc'])->name . ' ' . round($data['currentProfit'], 2) . ' ' . ($data['currentProfit'] >= 0 ? '(Profit)' : '(Loss)') . ' Amount: ' . $data['amount'] . '$';

        MailerService::sendFutureTradeDynamicEmail($data);
        return $data;
    }

    public static function getFeeDetails($orderId)
    {

        $openOrder = DB::table('live_trades_future_results')->where('orderId', $orderId)->first();

        if (!$openOrder) {
            return false;
        }

        $market = 'FUTURE';
        $trader = $openOrder->trade_acc;
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        $symbol = $openOrder->symbol;
        $timestamp = round(microtime(true) * 1000);

        // Generate the signature
        $queryString = "symbol=$symbol&orderId=$orderId&timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        // Make the API request
        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->get("https://fapi.binance.com/fapi/v1/userTrades", [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $trades = $response->json();
        return $trades;
    }


    public static function getPositionDetails($symbol, $trader)
    {
        $user = User::find($trader);
        if (!$user) {
            return false;
        }

        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;
        $timestamp = round(microtime(true) * 1000);

        // Generate the signature
        $queryString = "timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        // Make the API request to get positions
        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->get("https://fapi.binance.com/fapi/v2/positionRisk", [
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);


        $positions = $response->json();

        if (!$positions || isset($positions['code'])) {
            return false; // Return false if request fails or API returns an error
        }

        // Loop through positions to find the specific symbol
        foreach ($positions as $position) {
            if ($position['symbol'] === strtoupper($symbol) && abs($position['positionAmt']) > 0) {
                return [
                    'symbol' => $position['symbol'],
                    'positionAmt' => $position['positionAmt'], // Amount of asset held (positive = long, negative = short)
                    'entryPrice' => $position['entryPrice'], // Entry price of the position
                    'markPrice' => $position['markPrice'], // Current price of the asset
                    'unRealizedProfit' => $position['unRealizedProfit'], // Unrealized PnL
                    'liquidationPrice' => $position['liquidationPrice'], // Liquidation price
                    'marginType' => $position['marginType'], // Margin type (cross or isolated)
                    'leverage' => $position['leverage'], // Leverage used
                    'positionSide' => $position['positionSide'], // Position side (BOTH, LONG, SHORT)
                ];
            }
        }

        return false; // No open position for this symbol
    }

    public static function openMarketPosition($symbol, $tradeAmount, $position = 'BUY', $leverage, $trader, $trade = null)
    {

        $market = 'FUTURE';



        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');


        // Step 1: Set leverage

        $leverageUrl = $base_url . config('binance.endpoints.leverage');

        $leverageData = [
            "symbol" => $symbol,
            "leverage" => $leverage,
            "timestamp" => round(microtime(true) * 1000),
        ];

        $leverageQuery = http_build_query($leverageData);
        $leverageSignature = hash_hmac('sha256', $leverageQuery, $apiSecret);
        $leverageQuery .= "&signature=" . $leverageSignature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $leverageUrl . "?" . $leverageQuery);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $leverageResponse = curl_exec($ch);
        curl_close($ch);
        $leverageResponse = json_decode($leverageResponse, true);
        if (isset($leverageResponse['code']) && $leverageResponse['code'] < 0) {
            throw new Exception("Failed to set leverage: " . $leverageResponse['msg']);
        }
        // Step 2: Fetch trading rules
        $exchangeInfoUrl =  $base_url . config('binance.endpoints.exchange_info');
        $exchangeInfo = json_decode(file_get_contents($exchangeInfoUrl . "?symbol=$symbol"), true);

        foreach ($exchangeInfo['symbols'] as $excInfo) {
            if ($excInfo['symbol'] == $symbol) {
                $filters = $excInfo['filters'];
                break;
            }
        }

        // Extract LOT_SIZE filter values
        $lotSize = null;
        foreach ($filters as $filter) {
            if ($filter['filterType'] == 'LOT_SIZE') {
                $lotSize = $filter;
                break;
            }
        }

        if ($lotSize === null) {
            throw new Exception("LOT_SIZE filter not found for symbol $symbol");
        }

        // Step 3: Calculate position quantity
        $positionSize = $tradeAmount * $leverage; // Total position size with leverage
        $quantity = $positionSize / $current_price;      // Contract quantity based on the price

        // Adjust quantity to match LOT_SIZE step size
        $quantity = floor($quantity / $lotSize['stepSize']) * $lotSize['stepSize'];


        $url = $base_url . config('binance.endpoints.order');

        $timestamp =  round(microtime(true) * 1000);
        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();



        if (isset($response['code']) && $response['code'] < 0) {
            throw new Exception("Order failed: " . $response['msg']);
        }

        // Calculate liquidation price
        $entryPrice = $current_price; // Assuming trade executed at provided price
        $accountMargin = $tradeAmount; // User's margin
        $liquidationPrice = 0;
        if ($position === 'BUY') {
            $liquidationPrice = $entryPrice - ($accountMargin / ($quantity * $leverage));
        } else if ($position === 'SELL') {
            $liquidationPrice = $entryPrice + ($accountMargin / ($quantity * $leverage));
        }

        $data =  [
            'orderId' => $response['orderId'],
            'tradeId' => $trade ? $trade->id : null,
            'symbol' => $response['symbol'],
            'side' => $response['side'],
            'amount' => $tradeAmount,
            'type' => 'open',
            'qty' => $quantity,
            'leverage' => $leverage,
            'price' => $current_price,
            'trade_acc' => $trader,
            'liqPrice' => $liquidationPrice,
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('dynamic_trades_future_results')->insert(
            $data
        );
        MailerService::sendFutureTradeDynamicEmail($data);

        return $data;
    }

    public static function closeMarketPosition($openOrderId, $trade)
    {


        $openOrder = DB::table('dynamic_trades_future_results')->where('orderId', $openOrderId)->first();
        $market = 'FUTURE';
        $position = $openOrder->side == 'BUY' ? 'SELL' : 'BUY';

        $symbol = $openOrder->symbol;
        $trader = $openOrder->trade_acc;
        $quantity = $openOrder->qty;


        $current_price = BinanceApiService::getCurrentPrice($symbol, $market);

        $user = User::find($trader);
        $apiKey = $user->api_key;
        $apiSecret = $user->api_secret;
        $base_url = $market == 'FUTURE' ? config('binance.api.future_base_url') : config('binance.api.base_url');
        $url = $base_url . config('binance.endpoints.order');

        $timestamp =  round(microtime(true) * 1000);
        // Prepare query string for signature
        $queryString = http_build_query([
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $apiSecret);

        // Append signature to the query string
        $queryString .= '&signature=' . $signature;

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->asForm()->post($url, [
            'symbol' => $symbol,
            "side" => $position,
            "type" => "MARKET",
            'quantity' => strval($quantity),
            'timestamp' => $timestamp,
            'signature' => $signature
        ]);
        $response = $response->json();



        if (isset($response['code']) && $response['code'] < 0) {
            throw new Exception("Order failed: " . $response['msg']);
        }

        $data =  [
            'orderId' => $response['orderId'],
            'tradeId' => $trade->id,
            'symbol' => $response['symbol'],
            'side' => $response['side'],
            'amount' => $quantity * $current_price,
            'qty' => $quantity,
            'type' => 'close',
            'leverage' => $trade->leverage,
            'price' => $current_price,
            'trade_acc' => $trader,
            'liqPrice' => 0,
            'created_at' => Carbon::now('Asia/Karachi'),
        ];

        DB::table('dynamic_trades_future_results')->insert(
            $data
        );
        $data['subject'] =
            MailerService::sendFutureTradeDynamicEmail($data);


        return $data;
    }


    public static function getExchangeInfo()
    {
        $exchangeInfo = json_decode(file_get_contents(config('binance.api.future_base_url') . config('binance.endpoints.exchange_info')), true);
        return $exchangeInfo;
    }




    //  For handling stop loss to binance end
    public static function placeOrUpdateStopMarketOrder($symbol, $trader, $stopPrice, $openOrderId)
    {
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        // Get position details
        $positionDetails = self::getPositionDetails($symbol, $trader);
        dd($positionDetails);
        if (!$positionDetails || $positionDetails['positionAmt'] == 0) {
            // No open position found, return last close order details


            $openOrder = DB::table('live_trades_future_results')->where('orderId', $openOrderId)->first();
            $market = 'FUTURE';
            $position = $openOrder->side == 'BUY' ? 'SELL' : 'BUY';
            $symbol = $openOrder->symbol;
            $trader = $openOrder->trade_acc;
            $quantity = $openOrder->qty;


            $current_price = BinanceApiService::getCurrentPrice($symbol, $market);


            $response =  self::getLastCloseOrder($symbol, $trader);




            if (isset($response['code']) && $response['code'] < 0) {
                throw new Exception("Order failed: " . $response['msg']);
            }

            $currentProfit = 0;
            if ($position === 'BUY') {
                $currentProfit = (($openOrder->price - $current_price) / $openOrder->price) * 100;
            } else {
                $currentProfit = (($current_price - $openOrder->price) / $openOrder->price) * 100;
            }

            $data =  [
                'orderId' => $response['orderId'],
                'pairId' => $openOrder->pairId,
                'symbol' => $response['symbol'],
                'side' => $response['side'],
                'amount' => $openOrder->amount,
                'qty' => $quantity,
                'position' => $position === 'BUY' ? 'SHORT' : 'LONG',
                'type' => 'close',
                'trade_status' => 'close',
                'leverage' => 0,
                'price' => $current_price,
                'currentProfit' => $currentProfit,
                'trade_acc' => $trader,
                'liqPrice' => 0,

                'created_at' => Carbon::now('Asia/Karachi'),
            ];

            DB::table('live_trades_future_results')->insert(
                $data
            );



            $feeUsdt = 0;
            $realizedPnl = 0;

            // For close order
            $feeDetails = self::getFeeDetails($response['orderId']);

            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            // For close order
            $feeDetails = self::getFeeDetails($openOrderId);

            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            DB::table('live_trades_future_results')->where('orderId', $response['orderId'])->update([
                'trade_status' => 'close',
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,

            ]);


            DB::table('live_trades_future_results')->where('orderId', $openOrderId)->update([
                'trade_status' => 'close',
                'pairId' => $response['orderId'],
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,

            ]);
            $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position']  . ' ' . $openOrder->formula  . ' ' . $symbol . ' :: Account ' . User::find($data['trade_acc'])->name . ' ' . round($data['currentProfit'], 2) . ' ' . ($data['currentProfit'] >= 0 ? '(Profit)' : '(Loss)') . ' Amount: ' . $data['amount'] . '$';

            MailerService::sendFutureTradeDynamicEmail($data);
            return $data;
        }

        // Determine side: If position is LONG, stop-loss is a SELL. If SHORT, stop-loss is a BUY.
        $side = ($positionDetails['positionAmt'] > 0) ? 'SELL' : 'BUY';

        // Check for existing stop order
        $existingStopOrder = self::getExistingStopOrder($symbol, $trader, $side);
        // dd($existingStopOrder);
        if ($existingStopOrder) {
            // Cancel existing stop order
            self::cancelOrder($symbol, $trader, $existingStopOrder['orderId']);
        }

        // Place new stop order
        // Place new stop order
        $timestamp = round(microtime(true) * 1000);

        // Create parameters array
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'STOP_MARKET',
            'stopPrice' => $stopPrice,
            'quantity' => abs($positionDetails['positionAmt']),
            'timestamp' => $timestamp,
        ];

        // Convert to query string for signature
        $queryString = http_build_query($params);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        // Try using Guzzle directly for more control
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://fapi.binance.com/fapi/v1/order', [
            'headers' => [
                'X-MBX-APIKEY' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'query' => $params + ['signature' => $signature],
        ]);

        // Get the response body as a string
        $responseBody = $response->getBody()->getContents();

        // Decode the JSON response
        $jsonResponse = json_decode($responseBody, true);


        // Return the JSON response
        return $jsonResponse;
    }
    private static function getExistingStopOrder($symbol, $trader, $side)
    {
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol=$symbol&timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->get("https://fapi.binance.com/fapi/v1/openOrders", [
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $orders = $response->json();

        foreach ($orders as $order) {
            if ($order['side'] == $side && $order['type'] == 'STOP_MARKET') {
                return $order;
            }
        }

        return null;
    }
    private static function cancelOrder($symbol, $trader, $orderId)
    {
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol=$symbol&orderId=$orderId&timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->delete("https://fapi.binance.com/fapi/v1/order", [
            'symbol' => $symbol,
            'orderId' => $orderId,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        return $response->json();
    }
    private static function getLastCloseOrder($symbol, $trader)
    {
        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        $timestamp = round(microtime(true) * 1000);
        $queryString = "symbol=$symbol&timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        $response = self::getHttpClient()->withHeaders([
            'X-MBX-APIKEY' => $apiKey,
        ])->get("https://fapi.binance.com/fapi/v1/allOrders", [
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $orders = $response->json();

        // Find last closed order
        foreach (array_reverse($orders) as $order) {
            if ($order['status'] == 'FILLED' || $order['status'] == 'CANCELED') {
                return $order;
            }
        }

        return false;
    }










    // TP/SL Position functions

    /**
     * Place Take Profit and Stop Loss orders on an existing Binance Futures position
     * 
     * @param string $symbol The trading pair symbol (e.g. "BTCUSDT")
     * @param array $positionDetails The position details including positionAmt
     * @param float $takeProfitPrice The take profit price
     * @param float $stopLossPrice The stop loss price
     * @param string $apiKey Your Binance API key
     * @param string $secretKey Your Binance API secret key
     * @return array An array containing both order responses
     */
    public static function placeTpSlOrders($symbol, $trader, float $takeProfitPrice, float $stopLossPrice, $openOrderId)
    {


        $user = User::find($trader);
        $apiKey = $user->api_key;
        $secretKey = $user->api_secret;

        // Get position details
        $positionDetails = self::getPositionDetails($symbol, $trader);

        if (!$positionDetails || $positionDetails['positionAmt'] == 0) {
            // No open position found, return last close order details


            $openOrder = DB::table('live_trades_future_results')->where('orderId', $openOrderId)->first();
            $market = 'FUTURE';
            $position = $openOrder->side == 'BUY' ? 'SELL' : 'BUY';
            $symbol = $openOrder->symbol;
            $trader = $openOrder->trade_acc;
            $quantity = $openOrder->qty;


            $current_price = BinanceApiService::getCurrentPrice($symbol, $market);


            $response =  self::getLastCloseOrder($symbol, $trader);




            if (isset($response['code']) && $response['code'] < 0) {
                throw new Exception("Order failed: " . $response['msg']);
            }

            $currentProfit = 0;
            if ($position === 'BUY') {
                $currentProfit = (($openOrder->price - $current_price) / $openOrder->price) * 100;
            } else {
                $currentProfit = (($current_price - $openOrder->price) / $openOrder->price) * 100;
            }

            $data =  [
                'orderId' => $response['orderId'],
                'pairId' => $openOrder->pairId,
                'symbol' => $response['symbol'],
                'side' => $response['side'],
                'amount' => $openOrder->amount,
                'qty' => $quantity,
                'position' => $position === 'BUY' ? 'SHORT' : 'LONG',
                'type' => 'close',
                'trade_status' => 'close',
                'leverage' => 0,
                'price' => $current_price,
                'currentProfit' => $currentProfit,
                'trade_acc' => $trader,
                'liqPrice' => 0,

                'created_at' => Carbon::now('Asia/Karachi'),
            ];

            DB::table('live_trades_future_results')->insert(
                $data
            );



            $feeUsdt = 0;
            $realizedPnl = 0;

            // For close order
            $feeDetails = self::getFeeDetails($response['orderId']);

            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            // For close order
            $feeDetails = self::getFeeDetails($openOrderId);

            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            DB::table('live_trades_future_results')->where('orderId', $response['orderId'])->update([
                'trade_status' => 'close',
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,

            ]);


            DB::table('live_trades_future_results')->where('orderId', $openOrderId)->update([
                'trade_status' => 'close',
                'pairId' => $response['orderId'],
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,

            ]);
            $data['subject'] = 'Type:' . $data['type'] . ' ' . $data['position']  . ' ' . $openOrder->formula  . ' ' . $symbol . ' :: Account ' . User::find($data['trade_acc'])->name . ' ' . round($data['currentProfit'], 2) . ' ' . ($data['currentProfit'] >= 0 ? '(Profit)' : '(Loss)') . ' Amount: ' . $data['amount'] . '$';

            MailerService::sendFutureTradeDynamicEmail($data);
            return $data;
        }


        // Determine position side
        $positionAmt = $positionDetails['positionAmt'];
        $positionSide = $positionAmt > 0 ? 'LONG' : 'SHORT';

        // Set order sides based on position direction
        $tpSide = $positionSide === 'LONG' ? 'SELL' : 'BUY';
        $slSide = $positionSide === 'LONG' ? 'SELL' : 'BUY';

        // Absolute quantity (remove negative sign for short positions)
        $quantity = abs($positionAmt);

        // Place Take Profit order
        $tpOrder = self::placeOrder(
            $symbol,
            $tpSide,
            'TAKE_PROFIT_MARKET',
            $quantity,
            $takeProfitPrice,
            $apiKey,
            $secretKey
        );

        // Place Stop Loss order
        $slOrder = self::placeOrder(
            $symbol,
            $slSide,
            'STOP_MARKET',
            $quantity,
            $stopLossPrice,
            $apiKey,
            $secretKey
        );

        return [
            'takeProfit' => $tpOrder,
            'stopLoss' => $slOrder
        ];
    }

    /**
     * Place a single order on Binance Futures
     * 
     * @param string $symbol The trading pair symbol
     * @param string $side Order side (BUY or SELL)
     * @param string $type Order type (TAKE_PROFIT_MARKET or STOP_MARKET)
     * @param float $quantity Order quantity
     * @param float $triggerPrice The trigger price (stopPrice)
     * @param string $apiKey Binance API key
     * @param string $secretKey Binance API secret key
     * @return array The order response
     */
    private static function placeOrder($symbol, $side, $type, $quantity, $triggerPrice, $apiKey, $secretKey)
    {
        // Create timestamp
        $timestamp = round(microtime(true) * 1000);

        // Set up the parameters
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'quantity' => $quantity,
            'timestamp' => $timestamp,
            'reduceOnly' => 'true', // Ensures the order only reduces position
        ];

        // Add the appropriate price parameter based on order type
        if ($type === 'TAKE_PROFIT_MARKET') {
            $params['stopPrice'] = $triggerPrice;
            $params['priceProtect'] = 'true'; // Optional: Adds price protection
            $params['workingType'] = 'MARK_PRICE'; // Uses mark price as trigger
        } elseif ($type === 'STOP_MARKET') {
            $params['stopPrice'] = $triggerPrice;
            $params['priceProtect'] = 'true'; // Optional: Adds price protection
            $params['workingType'] = 'MARK_PRICE'; // Uses mark price as trigger
        }

        // Convert to query string for signature
        $queryString = http_build_query($params);

        // Generate signature
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        try {
            // Create Guzzle client
            $client = new \GuzzleHttp\Client();

            // Make the request
            $response = $client->request('POST', 'https://fapi.binance.com/fapi/v1/order', [
                'headers' => [
                    'X-MBX-APIKEY' => $apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'query' => $params + ['signature' => $signature],
            ]);

            // Parse and return the response
            $responseBody = $response->getBody()->getContents();
            return json_decode($responseBody, true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle errors and return error information
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                return [
                    'error' => true,
                    'message' => json_decode($errorBody, true),
                    'code' => $e->getCode()
                ];
            }

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
}
