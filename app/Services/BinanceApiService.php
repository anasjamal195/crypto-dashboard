<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public static function fetchBinanceUSDTPairs()
    {
        $url = config('binance.api.base_url') . config('binance.endpoints.exchange_info');

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
    public static function getCandleStickData($symbol = 'BTCUSDT', $interval = '15m', $limit = 100, $timestamp = '', $market = 'SPOT')
    {
        usleep(10000); // 10ms Sleep for safety
        // Choose the base URL based on the trade type
        $base_url = $market === 'FUTURE' ?
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

        return self::processData($response->json(), $market);
    }

    protected static function processData($data, $market = 'SPOT')
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
                // Initial trend assumption can actually be decided based on more context or prior data
                $trend = 'up';
                $sar = $low;  // Initial SAR
                $ep = $high;  // Extreme Point
                $af = 0.02;   // Acceleration Factor
            } else {
                $previousCandle = $data[$index - 1];
                $prevLow = (float) $previousCandle[3];
                $prevHigh = (float) $previousCandle[2];

                if ($trend == 'up') {
                    if ($high > $ep) {
                        $ep = $high;
                        $af = min($af + $afStep, $afMax);
                    }
                    $sar = min($sar + $af * ($ep - $sar), $low, $prevLow);
                    if ($low < $sar) {
                        $trend = 'down';
                        $sar = $ep;
                        $ep = $low;
                        $af = 0.02;
                    }
                } else {
                    if ($low < $ep) {
                        $ep = $low;
                        $af = min($af + $afStep, $afMax);
                    }
                    $sar = max($sar - $af * ($sar - $ep), $high, $prevHigh);
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

            $previousObvHigh = 0;
            if ($index > 15) {
                $previousObvHigh = $candlesticks[$index - 15]['obv'];

                for ($i = $index - 15; $i < $index; $i++) {
                    if ($previousObvHigh < $candlesticks[$i]['obv']) {
                        $previousObvHigh = $candlesticks[$i]['obv'];
                    }
                }
            }
            // Store candlestick data with all indicators
            $candlesticks[] = [
                'timestamp' => $timestamp,
                'market' => $market,
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
                'previousObvHigh' => $previousObvHigh,
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


        // return $response;
        $fee_details = self::getTotalCommission($response);

        if (!isset($response['code']) && $response['code'] == -2010) {
            Log::info('Trader ' . $trader . ': Sell response' . json_encode($response));
            DB::table('orders')
                ->where('orderId', $buy_order->orderId)
                ->where('trade_acc', $trader)
                ->update(
                    [
                        'pair_id' => 0,
                        'trade_status' => 'close',
                    ]
                );
            return false;
        }
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




    public static function placeDynamicBuyOrderSpot($symbol, $amount,  $trader)
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
            'amount' => $amount,
            'interval' => '1m',
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

        DB::table('dynamic_orders')->insert(
            $data
        );


        MailerService::sendEmail($data);
        return $data;
    }

    public static function placeDynamicSellOrderSpot($symbol, $quantity,  $trader)
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


        // return $response;
        $fee_details = self::getTotalCommission($response);

        if (!isset($response['symbol'])) {
            Log::info('Trader ' . $trader . ': Sell response' . json_encode($response));
        }
        $data =  [
            'symbol' => $response['symbol'],
            'amount' => $quantity * $current_price,
            'interval' => '1m',
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

        DB::table('dynamic_orders')->insert(
            $data
        );

        MailerService::sendEmail($data);

        return $data;
    }
}
