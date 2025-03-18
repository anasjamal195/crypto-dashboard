<?php

namespace App\Jobs\ThreadsMACD;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LongThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $tries = 1; // The job will only run once
    public $stopLoss = 1.5;
    public $targetProfit = 0.5;

    public $tradeInstance;
    public $supportResistance;
    public $formula;
    public $profitIncrementPercentage = 0.2;


    /**
     * Create a new job instance.
     */
    public function __construct($tradeInstance, $supportResistance)
    {
        $this->tradeInstance = $tradeInstance;
        $this->supportResistance = $supportResistance;
        $this->formula = 'MACD Multithread';
    }

    public function handle(): void
    {
        $symbol = $this->tradeInstance->symbol;
        $trade_acc = $this->tradeInstance->tradeAccount;


        // Fetching Candle Data
        // -------------------------------
        $data = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '5m', 300, null, 'FUTURE');
        $candle = $data[count($data) - 1];
        $secondLastcandle = $data[count($data) - 2];

        $data15m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '15m', 100, null, 'FUTURE');
        $candle15m = $data15m[count($data15m) - 1];
        $secondLastcandle15m = $data15m[count($data15m) - 2];

        $data30m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '30m', 100, null, 'FUTURE');
        $candle30m = $data30m[count($data30m) - 1];
        $secondLastcandle30m = $data30m[count($data30m) - 2];

        $data2h = BinanceApiService::getCandleStickDataPast($symbol, '2h', 100, null, 'FUTURE');
        $candle2h = $data2h[count($data2h) - 1];
        $secondLastCandle2h = $data2h[count($data2h) - 2];
        // ---------------------------------




        $priceCount = 20;

        $openTrade = true;

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        // $isWick = CommonHelpers::isCandleWick($data[count($data) - 1], 'upper', 5, $this->supportResistance[7]['resistance'], $this->tradeInstance->symbol, $priceCount);
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('LongThreadMACD: Skipped due to last order close time: ' . $symbol);
            }
        }

        // if ($isWick) {
        //     $openTrade = false;

        //     // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to Wick formation ' . $symbol);
        //     Log::info('LongThreadMACD: Retreating Due to upper wick');
        // }
        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }

        // Repeat checking these conditions until true, or coin price is out of bound
        // while (true) {
        //     Log::info('LongThreadMACD: Entering 1m Loop...');

        //     $openTrade = true;
        //     // Check for    candle direction on 1 min, if bearish than skip trade
        //     $data1m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '1m', 5, null, 'FUTURE');
        //     $data3m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '3m', 5, null, 'FUTURE');
        //     $candle3m = $data3m[count($data3m) - 1];
        //     $secondLastcandle3m = $data3m[count($data3m) - 2];
        //     $candle1m = $data1m[count($data1m) - 1];
        //     $secondLastcandle1m = $data1m[count($data1m) - 2];
        //     $thirdLastcandle1m = $data1m[count($data1m) - 3];


        //     if ($candle3m['close'] < $candle3m['open']) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to 3m candle direction ' . $symbol);
        //     }

        //     if ($secondLastcandle3m['close'] < $secondLastcandle3m['open']) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to second Last 3m candle direction ' . $symbol);
        //     }

        //     if ($candle1m['close'] < $candle1m['open']) {
        //         $openTrade = false;
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to 1m candle direction ' . $symbol);
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to 1m candle direction ' . $symbol);
        //     }

        //     $secondLastper = (($secondLastcandle1m['close'] - $secondLastcandle1m['open']) / $secondLastcandle1m['open']) * 100;
        //     if ($secondLastper < 0.08) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to second Last 1m candle percentage ' . $symbol);
        //     }
        //     // Check for second last 1m candle direction
        //     if ($secondLastcandle1m['close'] < $secondLastcandle1m['open']) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to second Last 1m candle direction ' . $symbol);
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to second Last 1m candle direction ' . $symbol);
        //     }

        //     // Check for second last 1m candle direction
        //     if ($thirdLastcandle1m['close'] < $thirdLastcandle1m['open']) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to third Last 1m candle direction ' . $symbol);
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to third Last 1m candle direction ' . $symbol);
        //     }

        //     $candleDiff1m  = abs($secondLastcandle1m['close'] - $secondLastcandle1m['open']);

        //     $upperWickDiff = $secondLastcandle1m['high'] - $secondLastcandle1m['close'];
        //     // Candle wick condition for second last candle 1m 
        //     if ($upperWickDiff > $candleDiff1m) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped opening LONG Due to 1m candle wick greated than solid region ' . $symbol);
        //     }

        //     if (
        //         $openTrade ||
        //         $candle1m['close'] > ($this->supportResistance[7]['resistance'] * (1 + 0.3 / 100))  ||
        //         $candle1m['close'] < ($this->supportResistance[7]['resistance'] * (1 - 1.2 / 100))
        //     ) {
        //         Log::info('LongThreadMACD: Timeout for 1m loop ' . $symbol . ' Trade status: ' . $openTrade);
        //         break;
        //     }
        //     // Update Cache to make this symbol unavailable
        //     Cache::put($symbol . '_availability', 0, now()->addMinute());
        //     CommonHelpers::delayS(5);
        // }



        // Temporarily Disabled
        // Check if it is allowed to open trade
        // $lastOpenTrade = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->first();
        // if ($lastOpenTrade) {

        //     if ($lastOpenTrade->currentProfit < 0.5) {
        //         $openTrade = false;
        //         Log::info('LongThreadMACD: Skipped due to current open order in loss: ' . $symbol);
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG due to current open order in loss ' . $symbol);
        //     }
        // } else {

        //     $lastClosed = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'close')->orderBy('created_at', 'DESC')->first();

        //     if ($lastClosed) {
        //         $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastClosed->created_at));
        //         if ($timeDiff <= 30 && $lastClosed->currentProfit <= 0) {
        //             $openTrade = false;
        //             Log::info('LongThreadMACD: Skipped due to last order closed in loss: ' . $symbol);
        //             // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG due to last order closed in loss ' . $symbol);
        //         }
        //     }
        // }


        $openTrades = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->get();
        $openTradesCount = count($openTrades);
        if ($openTradesCount >= 3) {
            $openTrade = false;
        }
        // $allInProfit = true;

        // if ($openTradesCount == 0) {
        //     $allInProfit = false;
        // }
        // foreach ($openTrades as $currentOpenOrders) {
        //     if ($currentOpenOrders->currentProfit < 0.5) {
        //         $allInProfit = false;
        //     }
        // }

        // if (!$allInProfit && $openTradesCount >= 5) {
        //     $openTrade = false;
        // }









        
        /*
        ========================NEW CONDITIONS ON 2h===================================
        */

        // Fetch 2-hour candlestick data


        $instantOpen = false;

        // Condition 6: Skip trade if MA7 is above both MA25 and MA99, and previous candle's percentage change is non-positive
        // OR
        // Condition 8: Skip trade if last 2 hrs candle's RSI is above 72 OR current 2 hrs candle's RSI is above 70

        if (($candle2h['ma7'] > $candle2h['ma25'] && $candle2h['ma7'] > $candle2h['ma99'] && $secondLastCandle2h['per'] <= 0)
            || ($secondLastCandle2h['rsi6'] > 72 || $candle2h['rsi6'] > 70)
        ) {
            $openTrade = false;
        }


        // Condition 5: Check for instant opening
        if (
            (($candle2h['ma7'] > $candle2h['ma25'] && $secondLastCandle2h['ma7'] < $secondLastCandle2h['ma25']) ||
                ($candle2h['ma7'] > $candle2h['ma99'] && $secondLastCandle2h['ma7'] < $secondLastCandle2h['ma99']))

            ||

            ($secondLastCandle2h['rsi6'] < 25 || $candle2h['rsi6'] < 20)

            ||
            ($secondLastcandle30m['rsi6'] < 12 || $candle15m['rsi6'] < 17)

        ) {
            $instantOpen = true;
        }

        // Calculate wick and solid region sizes
        $upperWick = $secondLastCandle2h['high'] - max($secondLastCandle2h['close'], $secondLastCandle2h['open']);
        $lowerWick = min($secondLastCandle2h['close'], $secondLastCandle2h['open']) - $secondLastCandle2h['low'];
        $solidRegion = abs($secondLastCandle2h['close'] - $secondLastCandle2h['open']);

        // Skip trade if it's not an instant opening and doesn't meet Conditions 1, 3, or 4
        if (
            !$instantOpen &&
            !(
                $secondLastCandle2h['per'] >= 0.15 || // Condition 1
                ($secondLastCandle2h['per'] < 0 && $lowerWick > $upperWick && $upperWick < $solidRegion * 0.1) || // Condition 3
                ($upperWick == 0 && $lowerWick > 0) // Condition 4
            )
        ) {
            $openTrade = false;
        }

        // Condition 2: Final check - skip trade if percentage change is positive and upper wick is greater than lower wick
        if ($secondLastCandle2h['per'] > 0 && $upperWick > $lowerWick) {
            $openTrade = false;
        }



        /*
        ===============================================================================
        */




        if ($openTrade) {

            $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
            if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                $supportResistanceArr = [
                    'support' => $this->supportResistance[7]['support'],
                    'resistance' => $this->supportResistance[7]['resistance'],
                ];
                Log::info('LongThreadMACD: Opening Position: ' . $symbol);
                BinanceApiService::openMarketPositionLiveTrader($this->tradeInstance->symbol, $this->tradeInstance->buyPrice, $this->tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $this->tradeInstance->leverage, $this->tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit);

                $tradeLoop = true;
                // Proceed trade until the position is closed
                while ($tradeLoop) {
                    try {
                        $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
                        if (!(isset($open_order['is_open']) && $open_order['is_open']))
                            $tradeLoop = false;
                        $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                        $candleData = $supportResistance['candleData'];

                        $tradeLoop = self::manageOpenOrder($this->tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                    } catch (\Exception $e) {
                        Log::error('LongThreadMACD: Error - ' . $e->getMessage());
                        Log::error($e->getTraceAsString());
                    }
                    CommonHelpers::delayS(1);
                }
            }
        } else {
            DB::table('trade_handler')->where('id', $this->tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);


            Log::info('LongThreadMACD: Failed to open trade: ' . $symbol);
        }
    }

    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {

        Log::info('LongThreadMACD: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];
        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;


        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
        Log::info('LongThreadMACD: Current profit ' . $currentProfit);



        if (($stopLoss > $buy_order['price'] && $currentCandle['close'] < $stopLoss) || ($stopLoss < $buy_order['price'] && $currentCandle['open'] < $stopLoss)) {
            // Checking Upper Wick Formation

            $lower_wick = CommonHelpers::isCandleWick($currentCandle, 'lower', 5, $stopLoss, $tradeInstance->symbol);

            if (!$lower_wick) {
                BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                    'previousPrice' => $currentCandle['close'],
                    'currentPrice' => $currentCandle['close'],
                    'currentProfit' => $currentProfit,
                    'targetProfit' => $targetProfit,
                ]);
                DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                    'isWorkerDispatched' => false,
                ]);
                return false;
            } else {

                // MailerService::sendSkipEmail($tradeInstance, 'Skipped closing LONG Due to Wick formation ' . $tradeInstance->symbol);
                Log::info('LongThreadMACD: Retreating Due to upper wick');
            }
        } else if ($currentProfit > $targetProfit) {

            DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([

                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
            ]);
        } else {

            $lastOrderOpen = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $tradeInstance->tradeAccount)->where('symbol', $tradeInstance->symbol)->where('trade_status', 'open')->orderBy('created_at', 'desc')->first();

            // if ($lastOrderOpen) {
            //     $lastOrderOpen = $lastOrderOpen->created_at;
            //     $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderOpen));
            //     if ($timeDiff > 5 && $timeDiff < 10) {
            //         $diff = $secondLastCandle['close'] - $secondLastCandle['open'];
            //         if (
            //             $currentCandle['close'] < $currentCandle['open']  && $currentCandle['close'] <= (max($secondLastCandle['close'], $secondLastCandle['open']) - ($diff * 0.6))
            //             && $currentCandle['rsi6'] < $secondLastCandle['rsi6'] * (1 - 0.25)
            //         ) {

            //             // Closing due to early detection
            //             BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
            //             DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
            //                 'previousPrice' => $currentCandle['close'],
            //                 'currentPrice' => $currentCandle['close'],
            //                 'currentProfit' => $currentProfit,
            //                 'targetProfit' => $targetProfit,

            //             ]);
            //             DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
            //                 'isWorkerDispatched' => false,
            //             ]);
            //             return false;
            //         }
            //     }
            // }
            DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit
            ]);
        }

        return true;
    }
}
