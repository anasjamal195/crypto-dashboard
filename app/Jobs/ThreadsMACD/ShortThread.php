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

class ShortThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 360000000;
    public $tries = 1; // The job will only run once
    public $stopLoss = 1.2;
    public $targetProfit = 0.5;

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
        $this->formula = 'MACD Multithread';
        $this->profitIncrementPercentage = 0.2;
    }


    public function handle(): void
    {
        $data = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '5m', 300, null, 'FUTURE');
        $symbol = $this->tradeInstance->symbol;
        $trade_acc = $this->tradeInstance->tradeAccount;
        $data30m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '30m', 5, null, 'FUTURE');
        $candle30m = $data30m[count($data30m) - 1];
        $secondLastcandle30m = $data30m[count($data30m) - 2];
        $thirdLastcandle30m = $data30m[count($data30m) - 3];
        $priceCount = 20;

        $openTrade = true;

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        // $isWick = CommonHelpers::isCandleWick($data[count($data) - 1], 'lower', 5, $this->supportResistance[7]['support'], $this->tradeInstance->symbol, $priceCount);
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('ShortThreadMACD: Skipped due to last order close time: ' . $symbol);
            }
        }
        // if ($isWick) {
        //     $openTrade = false;
        //     // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening SHORT Due to Wick formation ' . $symbol);
        //     Log::info('ShortThreadMACD: Retreating Due to upper wick');
        // }
        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }



        // while (true) {
        //     Log::info('ShortThreadMACD: Entering 1m Loop...');

        //     $data1m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '1m', 5, null, 'FUTURE');
        //     $data3m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '3m', 5, null, 'FUTURE');
        //     $candle3m = $data3m[count($data3m) - 1];
        //     $secondLastcandle3m = $data3m[count($data3m) - 2];
        //     $candle1m = $data1m[count($data1m) - 1];
        //     $secondLastcandle1m = $data1m[count($data1m) - 2];
        //     $thirdLastcandle1m = $data1m[count($data1m) - 3];
        //     $openTrade = true;

        //     if ($candle3m['close'] > $candle3m['open']) {
        //         $openTrade = false;
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to 3m candle direction ' . $symbol);
        //     }

        //     if ($secondLastcandle3m['close'] > $secondLastcandle3m['open']) {
        //         $openTrade = false;
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to second Last 3m candle direction ' . $symbol);
        //     }

        //     if ($candle1m['close'] > $candle1m['open']) {
        //         $openTrade = false;
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening SHORT Due to 1m candle direction ' . $symbol);
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to 1m candle direction ' . $symbol);
        //     }



        //     // JUST FOR TESTING

        //     $secondLastper = (($secondLastcandle1m['open'] - $secondLastcandle1m['close']) / $secondLastcandle1m['open']) * 100;
        //     if ($secondLastper < 0.08) {
        //         $openTrade = false;
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to second Last 1m candle percentage ' . $symbol);
        //     }

        //     // Check for second last 1m candle direction
        //     if ($secondLastcandle1m['close'] > $secondLastcandle1m['open']) {
        //         $openTrade = false;
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening Short Due to second Last 1m candle direction ' . $symbol);
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to second Last 1m candle direction ' . $symbol);
        //     }

        //     // Check for second last 1m candle direction
        //     if ($thirdLastcandle1m['close'] > $thirdLastcandle1m['open']) {
        //         $openTrade = false;
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening Short Due to third Last 1m candle direction ' . $symbol);
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to third Last 1m candle direction ' . $symbol);
        //     }


        //     $candleDiff1m  = abs($secondLastcandle1m['close'] - $secondLastcandle1m['open']);

        //     $lowerWickDiff = min($secondLastcandle1m['close'], $secondLastcandle1m['open']) - $secondLastcandle1m['low'];
        //     // Candle wick condition for second last candle 1m 
        //     if ($lowerWickDiff > $candleDiff1m) {
        //         $openTrade = false;
        //         Log::info('ShortThreadMACD: Skipped opening SHORT Due to 1m candle wick greated than solid region ' . $symbol);
        //     }

        //     if (
        //         $openTrade ||
        //         $candle1m['close'] < ($this->supportResistance[7]['support'] * (1 - 0.3 / 100))  ||
        //         $candle1m['close'] > ($this->supportResistance[7]['support'] * (1 + 1.2 / 100))
        //     ) {
        //         Log::info('ShortThreadMACD: Timeout for 1m loop ' . $symbol . ' Trade status: ' . $openTrade);
        //         break;
        //     }
        //     // Update Cache to make this symbol unavailable

        //     CommonHelpers::delayS(5);
        // }


        // // Check if it is allowed to open trade
        // $lastOpenTrade = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'SHORT')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->first();
        // if ($lastOpenTrade) {

        //     if ($lastOpenTrade->currentProfit < 0.2) {
        //         $openTrade = false;
        //         Log::info('ShortThreadMACD: Skipped due to current open order in loss: ' . $symbol);
        //         // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening SHORT due to current open order in loss ' . $symbol);
        //     }
        // } else {

        //     $lastClosed = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'SHORT')->where('trade_status', 'close')->orderBy('created_at', 'DESC')->first();

        //     if ($lastClosed) {
        //         $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastClosed->created_at));
        //         if ($timeDiff <= 30 && $lastClosed->currentProfit <= 0) {
        //             $openTrade = false;
        //             Log::info('ShortThreadMACD: Skipped due to last order closed in loss: ' . $symbol);
        //             // MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening SHORT due to last order closed in loss ' . $symbol);
        //         }
        //     }
        // }
        // Check Opposite Wick Direction
        $secondLastsolid30mLength = abs($secondLastcandle30m['close'] - $secondLastcandle30m['open']);
        $secondLastlowerWickDiff30m = $secondLastcandle30m['low'] - min($secondLastcandle30m['close'], $secondLastcandle30m['open']);
        $secondLastupperWickDiff30m = $secondLastcandle30m['high'] - max($secondLastcandle30m['close'], $secondLastcandle30m['open']);

        $isDownwardWick30m  = $secondLastlowerWickDiff30m > $secondLastsolid30mLength && $secondLastupperWickDiff30m < $secondLastsolid30mLength * 0.1;
        $isUpwardWick30m  = $secondLastlowerWickDiff30m < $secondLastsolid30mLength * 0.1 && $secondLastupperWickDiff30m > $secondLastsolid30mLength;

        if (

            ($secondLastcandle30m['per'] >= -0.07 || $thirdLastcandle30m['per'] >= -0.07)
            &&
            ($secondLastcandle30m['per'] <= -0.07 || !$isUpwardWick30m)
            &&
            $isDownwardWick30m

        ) {
            $openTrade = false;
            Log::info('ShortThreadMACD: Skipped opening SHORT Due to 30m candle conditions ' . $symbol);
        }


       

        $openTrades = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'SHORT')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->get();
        $openTradesCount = count($openTrades);
        if ($openTradesCount >= 3) {
            $openTrade = false;
        }
        // $allInProfit = true;

        // if ($openTradesCount == 0) {
        //     $allInProfit = false;
        // }
        // foreach ($openTrades as $openTrade) {
        //     if ($openTrade->currentProfit < 0.5) {
        //         $allInProfit = false;
        //     }
        // }

        // if (!$allInProfit && $openTradesCount >= 5) {
        //     $openTrade = false;
        // }

        if ($openTrade) {

            $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
            if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                $supportResistanceArr = [
                    'support' => $this->supportResistance[7]['support'],
                    'resistance' => $this->supportResistance[7]['resistance'],
                ];
                Log::info('ShortThreadMACD: Opening Position: ' . $symbol);
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
                        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

                        $tradeLoop = self::manageOpenOrder($this->tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                    } catch (\Exception $e) {
                        Log::error('ShortThreadMACD: Error - ' . $e->getMessage());
                        Log::error($e->getTraceAsString());
                    }
                    CommonHelpers::delayS(1);
                }
            }
        } else {

            DB::table('trade_handler')->where('id', $this->tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);
            Log::info('ShortThreadMACD: Failed to open trade: ' . $symbol);
        }
    }

    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {
        Log::info('ShortThreadMACD: Open order found for ' . $buy_order['symbol']);

        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];

        $stopLoss = $buy_order['stopLoss'];



        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
        Log::info('ShortThreadMACD: Current profit ' . $currentProfit);

        if ($currentCandle['close'] > $stopLoss) {
            $upper_wick = CommonHelpers::isCandleWick($currentCandle, 'upper', 5, $stopLoss, $tradeInstance->symbol);
            if (!$upper_wick) {
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
                Log::info('ShortThreadMACD: Retreating Due to lower wick');
                // MailerService::sendSkipEmail($tradeInstance, 'Skipped closing SHORT Due to Wick formation ' . $tradeInstance->symbol);
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


            $lastOrderOpen = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $tradeInstance->tradeAccount)->where('symbol', $tradeInstance->symbol)->where('trade_status', 'open')->orderBy('created_at', 'desc')->first();

            // if ($lastOrderOpen) {
            //     $lastOrderOpen = $lastOrderOpen->created_at;
            //     $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderOpen));
            //     if ($timeDiff > 5 && $timeDiff < 10) {
            //         $diff = $secondLastCandle['open'] - $secondLastCandle['close'];
            //         if (
            //             $currentCandle['close'] > $currentCandle['open']  && $currentCandle['close'] >= (min($secondLastCandle['close'], $secondLastCandle['open']) + ($diff * 0.6))
            //             && $currentCandle['rsi6'] > $secondLastCandle['rsi6'] * 1.25
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
                'targetProfit' => $targetProfit,

            ]);
        }

        return true;
    }
}
