<?php

namespace App\Jobs\Threads;

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
    public $tradeInstance;
    public $supportResistance;
    public $formula;
    public $profitIncrementPercentage;


    /**
     * Create a new job instance.
     */
    public function __construct($tradeInstance, $supportResistance)
    {
        $this->tradeInstance = $tradeInstance;
        $this->supportResistance = $supportResistance;
        $this->formula = 'Support/Resistance Breakout Multithread';
        $this->profitIncrementPercentage = 0.2;
    }


    public function handle(): void
    {
        $data = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '5m', 300, null, 'FUTURE');
        $symbol = $this->tradeInstance->symbol;
        $trade_acc = $this->tradeInstance->tradeAccount;
        $data3m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '3m', 5, null, 'FUTURE');
        $candle3m = $data3m[count($data3m) - 1];
        $secondLastcandle3m = $data3m[count($data3m) - 2];
        $priceCount = 20;
        if (
            $candle3m['close'] > $candle3m['open'] &&
            $secondLastcandle3m['close'] > $secondLastcandle3m['open']
        ) {
            $priceCount = 10;
        }
        // Wait for 20 sec for confirmation
        $openTrade = true;

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        $isWick = CommonHelpers::isCandleWick($data[count($data) - 1], 'upper', 5, $this->supportResistance[7]['resistance'], $this->tradeInstance->symbol, $priceCount);
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped due to last order close time: ' . $symbol);
            }
        }

        if ($isWick) {
            $openTrade = false;

            // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to Wick formation ' . $symbol);
            Log::info('FutureTraderLongEXP1: Retreating Due to upper wick');
        }
        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }

        // Repeat checking these conditions until true, or coin price is out of bound
        while (true) {
            Log::info('FutureTraderLongEXP1: Entering 1m Loop...');

            $openTrade = true;
            // Check for    candle direction on 1 min, if bearish than skip trade
            $data1m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '1m', 5, null, 'FUTURE');
            $data3m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '3m', 5, null, 'FUTURE');
            $candle3m = $data3m[count($data3m) - 1];
            $secondLastcandle3m = $data3m[count($data3m) - 2];
            $candle1m = $data1m[count($data1m) - 1];
            $secondLastcandle1m = $data1m[count($data1m) - 2];
            $thirdLastcandle1m = $data1m[count($data1m) - 3];


            if ($candle3m['close'] < $candle3m['open']) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to 3m candle direction ' . $symbol);
            }

            if ($secondLastcandle3m['close'] < $secondLastcandle3m['open']) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to second Last 3m candle direction ' . $symbol);
            }

            if ($candle1m['close'] < $candle1m['open']) {
                $openTrade = false;
                // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to 1m candle direction ' . $symbol);
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to 1m candle direction ' . $symbol);
            }

            $secondLastper = (($secondLastcandle1m['close'] - $secondLastcandle1m['open']) / $secondLastcandle1m['open']) * 100;
            if ($secondLastper < 0.08) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to second Last 1m candle percentage ' . $symbol);
            }
            // Check for second last 1m candle direction
            if ($secondLastcandle1m['close'] < $secondLastcandle1m['open']) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to second Last 1m candle direction ' . $symbol);
                // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to second Last 1m candle direction ' . $symbol);
            }

            // Check for second last 1m candle direction
            if ($thirdLastcandle1m['close'] < $thirdLastcandle1m['open']) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to third Last 1m candle direction ' . $symbol);
                // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to third Last 1m candle direction ' . $symbol);
            }

            $candleDiff1m  = abs($secondLastcandle1m['close'] - $secondLastcandle1m['open']);

            $upperWickDiff = $secondLastcandle1m['high'] - $secondLastcandle1m['close'];
            // Candle wick condition for second last candle 1m 
            if ($upperWickDiff > $candleDiff1m) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped opening LONG Due to 1m candle wick greated than solid region ' . $symbol);
            }

            if (
                $openTrade ||
                $candle1m['close'] > ($this->supportResistance[7]['resistance'] * (1 + 0.3 / 100))  ||
                $candle1m['close'] < ($this->supportResistance[7]['resistance'] * (1 - 1 / 100))
            ) {
                Log::info('FutureTraderLongEXP1: Timeout for 1m loop ' . $symbol . ' Trade status: ' . $openTrade);
                break;
            }
            // Update Cache to make this symbol unavailable
            Cache::put($symbol . '_availability', 0, now()->addMinute());
            CommonHelpers::delayS(5);
        }



        // Check if it is allowed to open trade
        $lastOpenTrade = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->first();
        if ($lastOpenTrade) {

            if ($lastOpenTrade->currentProfit < 0.2) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped due to current open order in loss: ' . $symbol);
                // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG due to current open order in loss ' . $symbol);
            }
        } else {

            $lastClosed = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'close')->orderBy('created_at', 'DESC')->first();

            if ($lastClosed) {
                $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastClosed->created_at));
                if ($timeDiff <= 30 && $lastClosed->currentProfit <= 0) {
                    $openTrade = false;
                    Log::info('FutureTraderLongEXP1: Skipped due to last order closed in loss: ' . $symbol);
                    // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG due to last order closed in loss ' . $symbol);
                }
            }
        }


        // Free cache with this worker on decision time
        $dispatchedWorkers = Cache::get('dispatched_workers', 0);
        Cache::put('dispatched_workers', $dispatchedWorkers ? $dispatchedWorkers-- : 0, now()->addDay());

        if ($openTrade) {
            Cache::put($symbol . '_availability', 0, now()->addMinute());
            $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
            if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                $supportResistanceArr = [
                    'support' => $this->supportResistance[7]['support'],
                    'resistance' => $this->supportResistance[7]['resistance'],
                ];
                BinanceApiService::openMarketPositionLiveTrader($this->tradeInstance->symbol, $this->tradeInstance->buyPrice, $this->tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $this->tradeInstance->leverage, $this->tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0);
            }

            $tradeLoop = true;
            // Proceed trade until the position is closed
            while ($tradeLoop) {
                try {
                    $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
                    if (!(isset($open_order['is_open']) && $open_order['is_open']))
                        $tradeLoop = false;
                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                    $candleData = $supportResistance['candleData'];
                    $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                    $tradeLoop = self::manageOpenOrder($this->tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                } catch (\Exception $e) {
                    Log::error('LongThread: Error - ' . $e->getMessage());
                    Log::error($e->getTraceAsString());
                }
                CommonHelpers::delayS(1);
            }
        } else {
            Cache::put($symbol . '_availability', 1, now()->addMinute());


            Log::info('FutureTraderLongEXP1: Failed to open trade: ' . $symbol);
        }
    }

    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {

        Log::info('FutureTraderLongEXP1: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];



        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
        Log::info('FutureTraderLongEXP1: Current profit ' . $currentProfit);

        if ($currentCandle['close'] < $stopLoss) {
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
                return false;
            } else {

                // MailerService::sendSkipEmail($tradeInstance, 'Skipped closing LONG Due to Wick formation ' . $tradeInstance->symbol);
                Log::info('FutureTraderLongEXP1: Retreating Due to upper wick');
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

            if ($lastOrderOpen) {
                $lastOrderOpen = $lastOrderOpen->created_at;
                $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderOpen));
                if ($timeDiff > 5 && $timeDiff < 10) {
                    $diff = $secondLastCandle['close'] - $secondLastCandle['open'];
                    if (
                        $currentCandle['close'] < $currentCandle['open']  && $currentCandle['close'] <= (max($secondLastCandle['close'], $secondLastCandle['open']) - ($diff * 0.6))
                        && $currentCandle['rsi6'] < $secondLastCandle['rsi6'] * (1 - 0.25)
                    ) {

                        // Closing due to early detection
                        BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
                        DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                            'previousPrice' => $currentCandle['close'],
                            'currentPrice' => $currentCandle['close'],
                            'currentProfit' => $currentProfit,
                            'targetProfit' => $targetProfit,

                        ]);
                        return false;
                    }
                }
            }
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
