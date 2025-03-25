<?php

namespace App\Jobs\ThreadsOrderBook;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
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

    public $triggerPrice = 0;
    public $triggerIndex = 0;


    /**
     * Create a new job instance.
     */
    public function __construct($tradeInstance, $supportResistance, $triggerPrice, $triggerIndex)
    {
        $this->triggerPrice = $triggerPrice;
        $this->triggerIndex = $triggerIndex;
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

        // $data15m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '15m', 100, null, 'FUTURE');
        // $candle15m = $data15m[count($data15m) - 1];
        // $secondLastcandle15m = $data15m[count($data15m) - 2];

        // $data30m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '30m', 100, null, 'FUTURE');
        // $candle30m = $data30m[count($data30m) - 1];
        // $secondLastcandle30m = $data30m[count($data30m) - 2];

        // $data2h = BinanceApiService::getCandleStickDataPast($symbol, '2h', 100, null, 'FUTURE');
        // $candle2h = $data2h[count($data2h) - 1];
        // $secondLastCandle2h = $data2h[count($data2h) - 2];
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


        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }



        $openTrades = DB::table('live_trades_future_results')->where('trade_acc', $this->tradeInstance->tradeAccount)->where('position', 'LONG')->where('trade_status', 'open')->orderBy('created_at', 'DESC')->get();
        $openTradesCount = count($openTrades);
        if ($openTradesCount >= 3) {
            $openTrade = false;
        }



        // Checking Trigger Price 
        while (true) {
            if ($candle['close'] <= $this->triggerPrice) {
                $timestamp = $candle['timestamp'];
                $snapshot = OrderBookSnapshot::where('snapshot_time', '>=', $timestamp)
                    ->where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                    ->where('symbol', $symbol)
                    ->where('depth', 1000)
                    ->where('signal', 'SHORT')
                    ->where('short_strength', '>=', 8)
                    ->latest('snapshot_time')
                    ->first();

                if (!$snapshot)
                    continue;
                break;
            } else if (((($candle['close'] - $this->triggerPrice) / $this->triggerPrice) * 100) >  5) {
                $openTrade = false;
                break;
            }
        }






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
