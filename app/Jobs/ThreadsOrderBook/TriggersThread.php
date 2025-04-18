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
use stdClass;

class TriggersThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    // Internal Use
    public $tries = 1; // The job will only run once
    public $timeout = 360000000;
    public $tradeInstance;
    public $supportResistance;
    public $triggerPrice = 0;
    public $triggerIndex = 0;
    public $workerId;
    public $account;


    // Meta data
    public $stopLoss = 1;
    public $nextSLTriggerTime = 30;
    public $slTriggerTimeInc = 30;
    public $targetProfit = 0.5;
    public $profitIncrementPercentage = 0.05;
    public $profitIncrementPercentageNext = 0.1;
    public $formula = 'MFI , MACD and OrderBook Imbalance';



    /**
     * Create a new job instance.
     */
    public function __construct($workerId, $account)
    {
        $this->workerId = $workerId;
        $this->account = $account;
    }

    public function handle(): void
    {
        while (true) {
            try {
                $tradeToOpen = null;
                $tradeType = null;
                // Main Loop to process coins list
                while (true) {
                    $worker_symbols = DB::table('worker_symbols')->where('worker_id', $this->workerId)->get();

                    foreach ($worker_symbols as $worker_symbol) {

                        try {
                            $symbol = $worker_symbol->symbol;
                            $tradeInstance = new stdClass;
                            $trade_acc = $this->account;


                            $data = BinanceApiService::getCandleStickData($symbol, '5m', 300, null, 'FUTURE');
                            $index = count($data) - 1;
                            // Decrement index to get last completed candle
                            $index--;
                            $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($data, [7]);



                            $timestamp = $data[$index]['timestampReadable'];
                            $snapshots = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                                ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
                                ->where('symbol', $symbol)
                                ->where('depth', 1000)
                                ->latest('snapshot_time')
                                ->get();
                            if (count($snapshots) < 1) {
                                CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                                continue;
                            }

                            $orderBookSnapshot = $snapshots[0];




                            // ==================Decision Block==================

                            $buyLongCondition = false;

                            if ($data[$index]['per'] > 0.08) {

                                $loopIndex = $index;
                                while ($data[$loopIndex]['per'] < 0 || $loopIndex == $index) {



                                    $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $loopIndex);

                                    if (!$orderBookSnapshotLoop) {
                                        break;
                                    }
                                    $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
                                    $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;


                                    $macdLongConditionLoop =
                                        $data[$loopIndex]['histogram'] > $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] < 0 // Current Candle should be light red
                                        && $data[$loopIndex - 1]['histogram'] < $data[$loopIndex - 2]['histogram'] && $data[$loopIndex - 1]['histogram'] < 0 // // Second Last Candle should be dark red
                                        && $data[$loopIndex - 2]['histogram'] < $data[$loopIndex - 3]['histogram'] && $data[$loopIndex - 2]['histogram'] < 0 // // Third Last Candle should be dark red
                                        && $data[$loopIndex - 3]['histogram'] < $data[$loopIndex - 4]['histogram'] && $data[$loopIndex - 3]['histogram'] < 0 // // Fourth Last Candle should be dark red
                                        && $data[$loopIndex - 4]['histogram'] < $data[$loopIndex - 5]['histogram'] && $data[$loopIndex - 4]['histogram'] < 0 // // Fifth Last Candle should be dark red
                                        && $data[$loopIndex - 5]['histogram'] < $data[$loopIndex - 6]['histogram'] && $data[$loopIndex - 5]['histogram'] < 0 // // Sixth Last Candle should be dark red
                                    ;


                                    $buyLongCondition =  $imbalance > 5 && $spread_pct < 0.1
                                        && $macdLongConditionLoop && $data[$loopIndex]['mfi'] < 30 && $orderBookSnapshot->volume_imbalance > 1
                                        && $data[$loopIndex]['K'] < 30
                                        && $data[$loopIndex]['J'] > $data[$loopIndex]['K'] && $data[$loopIndex]['J'] > $data[$loopIndex]['D'];


                                    $loopIndex--;

                                    if ($buyLongCondition)
                                        break;
                                }
                            }




                            $sellShortCondition = false;

                            if ($data[$index]['per'] < -0.08) {

                                $loopIndex = $index;
                                while ($data[$loopIndex]['per'] > 0 || $loopIndex == $index) {



                                    $orderBookSnapshotLoop = self::getOrderBookSnapshot($symbol, $data, $loopIndex);
                                    if (!$orderBookSnapshotLoop) {
                                        break;
                                    }
                                    $imbalance = ($orderBookSnapshotLoop->bid_volume - $orderBookSnapshotLoop->ask_volume) / ($orderBookSnapshotLoop->bid_volume + $orderBookSnapshotLoop->ask_volume) * 100;
                                    $spread_pct = ($orderBookSnapshotLoop->lowest_ask - $orderBookSnapshotLoop->highest_bid) / (($orderBookSnapshotLoop->lowest_ask + $orderBookSnapshotLoop->highest_bid) / 2) * 100;


                                    $macdShortConditionLoop =
                                        $data[$loopIndex]['histogram'] < $data[$loopIndex - 1]['histogram'] && $data[$loopIndex]['histogram'] > 0 // Current Candle should be solid green
                                        && $data[$loopIndex - 1]['histogram'] > $data[$loopIndex - 2]['histogram'] && $data[$loopIndex - 1]['histogram'] > 0 // // Second Last Candle should be light green
                                        && $data[$loopIndex - 2]['histogram'] > $data[$loopIndex - 3]['histogram'] && $data[$loopIndex - 2]['histogram'] > 0 // // Third Last Candle should be light green
                                        && $data[$loopIndex - 3]['histogram'] > $data[$loopIndex - 4]['histogram'] && $data[$loopIndex - 3]['histogram'] > 0 // // Fourth Last Candle should be light green
                                        && $data[$loopIndex - 4]['histogram'] > $data[$loopIndex - 5]['histogram'] && $data[$loopIndex - 4]['histogram'] > 0 // // Fifth Last Candle should be light green
                                        && $data[$loopIndex - 5]['histogram'] > $data[$loopIndex - 6]['histogram'] && $data[$loopIndex - 5]['histogram'] > 0 // // Sixth Last Candle should be light green
                                    ;
                                    $sellShortCondition =
                                        $macdShortConditionLoop
                                        && $data[$loopIndex]['ma7'] > $data[$loopIndex]['ma25']
                                        && $data[$loopIndex - 1]['ma7'] > $data[$loopIndex - 1]['ma25']
                                        && $data[$loopIndex]['obv'] < $data[$loopIndex - 1]['obv']
                                        && $data[$loopIndex]['K'] > 70
                                        && $data[$loopIndex]['J'] < $data[$loopIndex]['K'] && $data[$loopIndex]['J'] < $data[$loopIndex]['D'];
                                    $loopIndex--;

                                    if ($sellShortCondition)
                                        break;
                                }
                            }
                            if (
                                $buyLongCondition
                            ) {
                                $tradeType = 'LONG';
                            } elseif (
                                $sellShortCondition
                            ) {
                                $tradeType = 'SHORT';
                            } else {
                                CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                                continue;
                            }



                            // ========================================================================




                            // ===========Initiate Open Trade Process==================================
                            $tradeInstance = CommonHelpers::getTradeHandler($symbol, $this->account, $tradeType);


                            CommonHelpers::workerEngageSymbolOpenTrade($this->workerId, $tradeInstance);
                            $tradeToOpen =  $tradeInstance;
                            break;
                        } catch (\Exception $e) {
                            Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                            Log::error($e->getTraceAsString());
                        }
                        sleep(1);
                    }

                    // If an opening trade found than break the parent loop
                    if ($tradeToOpen)
                        break;
                }



                // Here we can add functionality to process trade that meets triggers

                $openTrade = true;
                $trade_acc = $tradeToOpen->tradeAccount;
                $symbol = $tradeToOpen->symbol;
                $tradeInstance = $tradeToOpen;


                // Fixed wait after each trade (Skipped for now)
                // $lastOrderClose = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                // if ($lastOrderClose) {
                //     $lastOrderClose = $lastOrderClose->created_at;
                //     $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
                //     if ($timeDiff < 20) {
                //         $openTrade = false;
                //         Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Skipped due to last order close time: ' . $symbol);
                //     }
                // }

                $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
                // Condition to limit open orders for a symbol in long or short
                if ($currentOpenOrders >= 1) {
                    $openTrade = false;
                }


                // Check candle closing 
                $isCandleClosing = (now()->timestamp - $data[count($data) - 1]['binance_timestamp'] / 1000) <= 40;

                if (!$isCandleClosing) {

                    Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to candle closing: ' . $symbol);

                    $openTrade = false;
                }

                if ($tradeType === 'LONG' && !CommonHelpers::getSettingsValue('enable_long_multithread', 0)) {
                    CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                    $openTrade = false;
                    Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to LONG Disabled: ' . $symbol);
                }

                if ($tradeType === 'SHORT' && !CommonHelpers::getSettingsValue('enable_short_multithread', 0)) {
                    CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                    $openTrade = false;
                    Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to SHORT Disabled: ' . $symbol);
                }


                if ($openTrade) {

                    $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);
                    if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                        $supportResistanceArr = [
                            'support' => 1,
                            'resistance' => 1,
                        ];
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Opening Position: ' . $symbol);

                        try {
                            BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit);
                        } catch (\Throwable $th) {
                            CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                            Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $th);
                            Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Skipping Opening Position due to error: ' . $symbol);
                            continue;
                        }

                        $tradeLoop = true;
                        // Proceed trade until the position is closed
                        while ($tradeLoop) {
                            try {
                                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);
                                if (!(isset($open_order['is_open']) && $open_order['is_open']))
                                    $tradeLoop = false;
                                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                                if ($tradeType === 'LONG')
                                    $tradeLoop = self::manageOpenOrderLong($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                                else if ($tradeType === 'SHORT')
                                    $tradeLoop = self::manageOpenOrderShort($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                            } catch (\Exception $e) {
                                Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                                Log::error($e->getTraceAsString());
                            }
                            CommonHelpers::delayS(1);
                        }

                        // Trade Completion, Remove and free this coin from this worker and prepare for next iteration
                        CommonHelpers::workerFreeAllSymbols($this->workerId, $this->account);
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Trade Successfully Closed: ' . $symbol);
                    }
                } else {
                    // Trade Failure
                    CommonHelpers::workerFreeAllSymbols($this->workerId, $this->account);

                    Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Failed to open trade: ' . $symbol);
                }


                // Recall this loop after every successful trade
                sleep(5);
            } catch (\Exception $e) {
                Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }
    }

    private static function manageOpenOrderLong($tradeInstance,  $open_order, $supportResistance, $profitIncrementPercentage, $workerId)
    {

        Log::info('TriggersThreadOrderBook ' . $workerId . ': Open order found for ' . $open_order['symbol']);
        $targetProfit = $open_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $thirdLastCandle = $candleData[count($candleData) - 3];
        $stopLoss = $open_order['stopLoss'];
        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;


        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': Current profit ' . $currentProfit);

        // Change take profit levels when order is stuck for more than 80 mins
        if (abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at'])) > 40 && $targetProfit <= 0.4) {
            $targetProfit = 0.2;
            Log::info('TriggersThreadOrderBook ' . $workerId . ': Profit Ratio changed due to trade getting stuck: ' . $open_order['symbol']);
        }

        if ($currentProfit < 0.5) {
            $profitIncrementPercentage = 0.05;
        } else {
            $profitIncrementPercentage = 0.1;
        }

        // Handle Early Closing on Order Books

        $closeEarly = false;

        // Reduce Stop loss by half every 30 min
        $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at']));

        $stopLossPercentage = 1 - (max(0, min(30, intval($timeDiff))) / 30);

        if ($stopLoss < $open_order['price'] && $currentCandle['per'] < 0)
            $stopLoss = $open_order['price'] * (1 - $stopLossPercentage / 100);

        // Gradually Narrow Stop Loss if profit is between volatility zone
        // if ($currentProfit > $open_order['currentProfit'] && $currentProfit > 0.2 && $currentProfit < 0.5) {
        //     $stopLoss = $currentCandle['close'] * (1 - 0.2 / 100);
        // }


        if ($currentCandle['close'] < $stopLoss || $closeEarly) {
            // Checking Upper Wick Formation

            BinanceApiService::closeMarketPositionLiveTrader($open_order['orderId']);
            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
            ]);
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);
            // Reset Trigger Time for stop loss
            // self::$nextSLTriggerTime = 30;
            return false;
        } else if ($currentProfit > $targetProfit) {

            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
            ]);
        } else {

            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],

                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'stopLoss' => $stopLoss,
            ]);
        }

        return true;
    }


    private static function manageOpenOrderShort($tradeInstance,  $open_order, $supportResistance, $profitIncrementPercentage, $workerId)
    {
        Log::info('ShortThreadOrderBook: Open order found for ' . $open_order['symbol']);

        $targetProfit = $open_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $thirdLastCandle = $candleData[count($candleData) - 3];

        $stopLoss = $open_order['stopLoss'];


        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100 * -1;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': Current profit ' . $currentProfit);


        // Change take profit levels when order is stuck for more than 80 mins
        if (abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at'])) > 40 && $targetProfit <= 0.4) {
            $targetProfit = 0.2;
            Log::info('TriggersThreadOrderBook ' . $workerId . ': Profit Ratio changed due to trade getting stuck: ' . $open_order['symbol']);
        }

        if ($currentProfit < 0.5) {
            $profitIncrementPercentage = 0.05;
        } else {
            $profitIncrementPercentage = 0.1;
        }


        // Handle Early Closing on Order Books

        $closeEarly = false;


        // Reduce Stop loss by half every 30 min
        $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at']));


        $stopLossPercentage = 1 - (max(0, min(30, intval($timeDiff))) / 30);
        if ($stopLoss > $open_order['price'] && $currentCandle['per'] > 0)
            $stopLoss = $open_order['price'] * (1 + $stopLossPercentage / 100);


        // Gradually Narrow Stop Loss if profit is between volatility zone
        // if ($currentProfit > $open_order['currentProfit'] && $currentProfit > 0.2 && $currentProfit < 0.5) {
        //     $stopLoss = $currentCandle['close'] * (1 + 0.2 / 100);
        // }

        if ($currentCandle['close'] > $stopLoss || $closeEarly) {

            BinanceApiService::closeMarketPositionLiveTrader($open_order['orderId']);
            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
            ]);
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);
            // Reset Trigger Time for stop loss
            // self::$nextSLTriggerTime = 30;
            return false;
        } else if ($currentProfit > $targetProfit) {

            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
            ]);
        } else {
            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'stopLoss' =>  $stopLoss,
            ]);
        }

        return true;
    }

    public static function getOrderBookSnapshot($symbol, $data, $index)
    {

        // Fetch OrderBook snapshot
        $timestamp = $data[$index]['timestampReadable'];
        $snapshot = OrderBookSnapshot::where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
            ->where('snapshot_time', '>=', Carbon::parse($timestamp)->subMinutes(60))
            ->where('symbol', $symbol)
            ->where('depth', 1000)
            ->latest('snapshot_time')
            ->first();
        return $snapshot;
    }
}
