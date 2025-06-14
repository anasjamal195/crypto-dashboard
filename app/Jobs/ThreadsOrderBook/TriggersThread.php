<?php

namespace App\Jobs\ThreadsOrderBook;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\HyperLiquidApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\SupportResistanceAnalyzer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

class TriggersThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    // Internal Use
    public $tries = 1; // The job will only run once
    public $timeout = 360000000;
    public $tradeInstance;
    public $supportResistanceCandleSpan = 3;
    public static $interval = '15m';
    public static $isSpot;
    public $supportResistance;
    public $triggerPrice = 0;
    public $triggerIndex = 0;
    public $workerId;
    public $account;
    public $openOrderIdRestarted;
    public static $activeExchange;

    // Meta data
    public $stopLoss = 0.8;
    public $nextSLTriggerTime = 30;
    public $slTriggerTimeInc = 30;
    public $targetProfit = 1;
    public $profitIncrementPercentage = 0.05;
    public $profitIncrementPercentageNext = 0.1;
    public $formula = 'MACD Swings with accuracy filteration (15m)';

    // Confirmed Trades Entries

    public static $candlesToCheck = 1000;
    public static $volumeMA5ValidFor = 1000;
    public static $upperWickValidFor = 1000;
    public static $bollSqueezValidFor = 1000;


    /**
     * Create a new job instance.
     */
    public function __construct($workerId, $account, $openOrderIdRestarted = null)
    {
        $this->workerId = $workerId;
        $this->account = $account;
        $this->openOrderIdRestarted = $openOrderIdRestarted;
    }

    public function handle(): void
    {
        while (true) {


            CommonHelpers::updateWorkerTicker($this->workerId);
            // Handle restarted worker
            $this->manageRestartedWorker($this->openOrderIdRestarted);

            // Set Active Exchange
            self::$activeExchange = CommonHelpers::getMetaValue($this->account, 'active_exchange', 'binance');
            // Check if any trade is open while worker was restarted
            try {
                $tradeToOpen = null;
                $tradeType = null;
                // Main Loop to process coins list
                while (true) {
                    CommonHelpers::updateWorkerTicker($this->workerId);

                    $worker_symbols = DB::table('worker_symbols')->where('worker_id', $this->workerId)->get();

                    foreach ($worker_symbols as $worker_symbol) {
                        CommonHelpers::updateWorkerTicker($this->workerId);

                        try {
                            $symbol = $worker_symbol->symbol;
                            $tradeInstance = new stdClass;
                            $trade_acc = $this->account;
                            // Log::info("Test Request Params" . self::$interval);


                            $data = self::$activeExchange === 'binance' ?
                                BinanceApiService::getCandleStickDataExternal($symbol, self::$interval, 500, null, self::$isSpot ? 'SPOT' : 'FUTURE')
                                : HyperLiquidApiService::getCandleStickDataExternal($symbol, self::$interval, 500, null, self::$isSpot ? 'SPOT' : 'FUTURE');

                            $index = count($data) - 1;
                            // Decrement index to get last completed candle
                            $index--;

                            // ==================Decision Block==================

                            $buyLongCondition = self::handleOpeningConditionsLong($symbol, $data, $index);
                            $sellShortCondition = self::handleOpeningConditionsShort($symbol, $data, $index);

                            // This block checks which weather to open trades or not
                            if (
                                ($buyLongCondition && $sellShortCondition)
                                ||
                                (!$buyLongCondition && !$sellShortCondition)
                            ) {
                                CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                                continue;
                            } else if ($buyLongCondition) {
                                $tradeType = 'LONG';
                            } else if ($sellShortCondition) {
                                $tradeType = 'SHORT';
                            }

                            Log::info("Conditions Met " . $symbol);

                            // ========================================================================


                            // ===========Initiate Open Trade Process==================================
                            $tradeInstance = CommonHelpers::getTradeHandler($symbol, $this->account, $tradeType, self::$interval);

                            if (!$tradeInstance) {
                                break;
                            }
                            Log::info("Trade instance found " . $symbol);

                            CommonHelpers::workerEngageSymbolOpenTrade($this->workerId, $tradeInstance);
                            $tradeToOpen =  $tradeInstance;
                            Log::info("Opening Confirmed in main loop, breaking to open... " . $symbol);

                            break;
                        } catch (\Exception $e) {
                            Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                            Log::error($e->getTraceAsString());
                        }

                        // Worker Tickers


                        sleep(2);
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




                $currentOpenOrders = 0;

                if (self::$isSpot && $tradeType === 'LONG')
                    $currentOpenOrders = DB::table('live_trades_spot_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
                else
                    $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();

                // Condition to limit open orders for a symbol in long or short
                if ($currentOpenOrders >= 1) {
                    $openTrade = false;
                }

                Log::info("No open orders found, progressing to open... " . $symbol);

                // Check candle closing 
                // $isCandleClosing = (now()->timestamp - $data[count($data) - 1]['binance_timestamp'] / 1000) <= 40;

                // if (!$isCandleClosing) {

                //     Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to candle closing: ' . $symbol);

                //     $openTrade = false;
                // }

                self::$isSpot = CommonHelpers::getMetaValue($this->account, 'enable_spot', 0);
                if (!self::$isSpot) {

                    if ($tradeType === 'LONG' && !CommonHelpers::getMetaValue($this->account, 'enable_long_multithread', 0)) {
                        CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                        $openTrade = false;
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to LONG Disabled: ' . $symbol);
                    }

                    if ($tradeType === 'SHORT' && !CommonHelpers::getMetaValue($this->account, 'enable_short_multithread', 0)) {
                        CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                        $openTrade = false;
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to SHORT Disabled: ' . $symbol);
                    }
                } else {

                    if ($tradeType === 'SHORT') {
                        $openTrade = false;
                    }

                    Log::info("Opening on spot... " . $symbol);
                }





                if ($openTrade) {

                    // Handle if current market is SPOT


                    $open_order = null;

                    if (self::$isSpot && $tradeType === 'LONG')
                        $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'SPOT', $trade_acc);
                    else
                        $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);




                    if (!(isset($open_order['is_open']) && $open_order['is_open'])) {

                        $supportResistanceArr = [
                            'support' => 1,
                            'resistance' => 1,
                        ];
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Opening Position: ' . $symbol);

                        try {
                            if (self::$isSpot && $tradeType === 'LONG') {
                                self::$activeExchange === 'binance' ?
                                    BinanceApiService::placeBuyOrderSpot($tradeInstance->symbol, $tradeInstance->buyPrice,  'BUY', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit)
                                    : HyperLiquidApiService::placeBuyOrderSpot($tradeInstance->symbol, $tradeInstance->buyPrice,  'BUY', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit);
                            } else {
                                self::$activeExchange === 'binance' ?
                                    BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit)
                                    : HyperLiquidApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit);
                            }
                        } catch (\Throwable $th) {
                            CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                            Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $th);
                            Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Skipping Opening Position due to error: ' . $symbol);
                            continue;
                        }

                        $tradeLoop = true;
                        // Proceed trade until the position is closed
                        while ($tradeLoop) {
                            CommonHelpers::updateWorkerTicker($this->workerId);

                            try {
                                $open_order = null;

                                if (self::$isSpot && $tradeType === 'LONG')
                                    $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'SPOT', $trade_acc);
                                else
                                    $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);


                                if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                                    $tradeLoop = false;
                                    break;
                                }
                                $supportResistance = null;


                                if (self::$isSpot && $tradeType === 'LONG')
                                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, self::$interval, 'SPOT', [$this->supportResistanceCandleSpan], null, false);
                                else
                                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, self::$interval, 'FUTURE', [$this->supportResistanceCandleSpan], null, false);


                                if ($tradeType === 'LONG')
                                    $tradeLoop = $this->manageOpenOrderLong($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                                else if ($tradeType === 'SHORT')
                                    $tradeLoop = $this->manageOpenOrderShort($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                            } catch (\Exception $e) {
                                Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                                Log::error($e->getTraceAsString());
                            }
                            CommonHelpers::delayS(2);
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

    private  function manageOpenOrderLong($tradeInstance,  $open_order, $supportResistance, $profitIncrementPercentage, $workerId)
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
        // $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at']));

        // $stopLossPercentage = 1 - (max(0, min(30, intval($timeDiff))) / 30);

        // if ($stopLoss < $open_order['price'] && $currentCandle['per'] < 0)
        //     $stopLoss = $open_order['price'] * (1 - $stopLossPercentage / 100);

        // Gradually Narrow Stop Loss if profit is between volatility zone
        // if ($currentProfit > $open_order['currentProfit'] && $currentProfit > 0.2 && $currentProfit < 0.5) {
        //     $stopLoss = $currentCandle['close'] * (1 - 0.2 / 100);
        // }

        // Check if SPOT enabled
        $tableName = $open_order['market'] === 'FUTURE' ? 'live_trades_future_results' : 'live_trades_spot_results';


        if ($currentCandle['close'] < $stopLoss || $closeEarly) {
            // Checking Upper Wick Formation

            if ($open_order['market'] === 'SPOT') {
                self::$activeExchange === 'binance' ?
                    BinanceApiService::placeSellOrderSpot($open_order['orderId'])
                    : HyperLiquidApiService::placeSellOrderSpot($open_order['orderId']);
            } else {
                self::$activeExchange === 'binance' ?
                    BinanceApiService::closeMarketPositionLiveTrader($open_order['orderId'])
                    : HyperLiquidApiService::closeMarketPositionLiveTrader($open_order['orderId']);
            }


            DB::table($tableName)->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);
            // Reset Trigger Time for stop loss
            // $this->nextSLTriggerTime = 30;
            return false;
        } else if ($currentProfit > $targetProfit) {
            // Update TP SL orders on binance also

            if (!self::$isSpot) {

                $takeProfitPercentage = $targetProfit + $profitIncrementPercentage;
                $takeProfitPrice = $currentCandle['close'] * (1 + $takeProfitPercentage / 100);
                $stopLossPrice = $currentCandle['close'];

                $tpSlOrders = self::$activeExchange === 'binance' ?
                    BinanceApiService::placeTpSlOrders($open_order['symbol'], $open_order['trade_acc'], $takeProfitPrice, $stopLossPrice, $open_order['orderId'])
                    : HyperLiquidApiService::placeTpSlOrders($open_order['symbol'], $open_order['trade_acc'], $takeProfitPrice, $stopLossPrice, $open_order['orderId']);
                if (!($tpSlOrders['takeProfit'] && $tpSlOrders['stopLoss'])) {
                    return false;
                }
                self::$activeExchange === 'binance' ?
                    BinanceApiService::updateTradeDetails($open_order['orderId'], $takeProfitPrice, $stopLossPrice, $tpSlOrders['takeProfit']['orderId'], $tpSlOrders['stopLoss']['orderId'], 'PENDING')
                    : HyperLiquidApiService::updateTradeDetails($open_order['orderId'], $takeProfitPrice, $stopLossPrice, $tpSlOrders['takeProfit']['orderId'], $tpSlOrders['stopLoss']['orderId'], 'PENDING');
            }

            DB::table($tableName)->where('orderId', $open_order['orderId'])->update([
                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
        } else {

            DB::table($tableName)->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'stopLoss' => $stopLoss,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
        }

        return true;
    }


    private function manageOpenOrderShort($tradeInstance,  $open_order, $supportResistance, $profitIncrementPercentage, $workerId)
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
        // $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($open_order['created_at']));


        // $stopLossPercentage = 1 - (max(0, min(30, intval($timeDiff))) / 30);
        // if ($stopLoss > $open_order['price'] && $currentCandle['per'] > 0)
        //     $stopLoss = $open_order['price'] * (1 + $stopLossPercentage / 100);


        // Gradually Narrow Stop Loss if profit is between volatility zone
        // if ($currentProfit > $open_order['currentProfit'] && $currentProfit > 0.2 && $currentProfit < 0.5) {
        //     $stopLoss = $currentCandle['close'] * (1 + 0.2 / 100);
        // }

        if ($currentCandle['close'] > $stopLoss || $closeEarly) {

            self::$activeExchange === 'binance' ?
                BinanceApiService::closeMarketPositionLiveTrader($open_order['orderId'])
                : HyperLiquidApiService::closeMarketPositionLiveTrader($open_order['orderId']);
            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'isWorkerDispatched' => false,
            ]);
            // Reset Trigger Time for stop loss
            // $this->nextSLTriggerTime = 30;
            return false;
        } else if ($currentProfit > $targetProfit) {


            if (!self::$isSpot) {

                $takeProfitPercentage = $targetProfit + $profitIncrementPercentage;
                $takeProfitPrice = $currentCandle['close'] * (1 - $takeProfitPercentage / 100);
                $stopLossPrice = $currentCandle['close'];

                $tpSlOrders =  self::$activeExchange === 'binance' ?
                    BinanceApiService::placeTpSlOrders($open_order['symbol'], $open_order['trade_acc'], $takeProfitPrice, $stopLossPrice, $open_order['orderId'])
                    : HyperLiquidApiService::placeTpSlOrders($open_order['symbol'], $open_order['trade_acc'], $takeProfitPrice, $stopLossPrice, $open_order['orderId']);

                if (!($tpSlOrders['takeProfit'] && $tpSlOrders['stopLoss'])) {
                    return false;
                }

                self::$activeExchange === 'binance' ?
                    BinanceApiService::updateTradeDetails($open_order['orderId'], $takeProfitPrice, $stopLossPrice, $tpSlOrders['takeProfit']['orderId'], $tpSlOrders['stopLoss']['orderId'], 'PENDING')
                    : HyperLiquidApiService::updateTradeDetails($open_order['orderId'], $takeProfitPrice, $stopLossPrice, $tpSlOrders['takeProfit']['orderId'], $tpSlOrders['stopLoss']['orderId'], 'PENDING');
            }

            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
        } else {
            DB::table('live_trades_future_results')->where('orderId', $open_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit,
                'stopLoss' =>  $stopLoss,
                'updated_at' => Carbon::now()->toDateTimeString(),

            ]);
        }

        return true;
    }

    public function manageRestartedWorker($openOrderId)
    {
        if ($openOrderId) {

            $lastOpenOrder = DB::table('live_trades_future_results')->where('orderId', $openOrderId)->first();
            $tradeInstance = CommonHelpers::getTradeHandler($lastOpenOrder->symbol, $this->account, $lastOpenOrder->position, self::$interval);

            $tradeType = $lastOpenOrder->position;
            $symbol = $lastOpenOrder->symbol;
            $trade_acc = $this->account;

            Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Open order found on restart: ' . $symbol);

            // Handle Opened Trades 
            $tradeLoop = true;
            // Proceed trade until the position is closed
            while ($tradeLoop) {
                CommonHelpers::updateWorkerTicker($this->workerId);
                try {
                    $open_order = null;

                    if (self::$isSpot && $tradeType === 'LONG')
                        $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'SPOT', $trade_acc);
                    else
                        $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);

                    if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                        $tradeLoop = false;
                        break;
                    }
                    $supportResistance = null;

                    if (self::$isSpot && $tradeType === 'LONG')
                        $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, self::$interval, 'SPOT', [$this->supportResistanceCandleSpan], null, false);
                    else
                        $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, self::$interval, 'FUTURE', [$this->supportResistanceCandleSpan], null, false);


                    if ($tradeType === 'LONG')
                        $tradeLoop = $this->manageOpenOrderLong($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                    else if ($tradeType === 'SHORT')
                        $tradeLoop = $this->manageOpenOrderShort($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                } catch (\Exception $e) {
                    Log::error('TriggersThreadOrderBook ' . $this->workerId . ': Error - ' . $e->getMessage());
                    Log::error($e->getTraceAsString());
                }
                CommonHelpers::delayS(2);
            }

            // Trade Completion, Remove and free this coin from this worker and prepare for next iteration
            CommonHelpers::workerFreeAllSymbols($this->workerId, $this->account);
            $this->openOrderIdRestarted = null;
            Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Restarted Trade Successfully Closed: ' . $symbol);
        }
    }






    // Function to check opening Conditions

    public static function handleOpeningConditionsLong($symbol, $data, $index)
    {
        if ($index == -2) {
            return null;
        }
        // LONG Entry
        if (self::checkConditionSetLongMACD($symbol, $data, $index) === 'LONG') {
            return 'LONG';
        } else if (self::checkConditionSetLongSR($symbol, $data, $index) === 'LONG') {
            return 'LONG';
        }
        // No conditions met so return null
        return null;
    }

    public static function handleOpeningConditionsShort($symbol, $data, $index)
    {

        if ($index == -2) {
            return null;
        }

        // SHORT Entry
        if (self::checkConditionSetShortMACD($symbol, $data, $index) === 'SHORT') {
            return 'SHORT';
        } else if (self::checkConditionSetShortSR($symbol, $data, $index) === 'SHORT') {
            return 'SHORT';
        }
        return null;
    }





    // Other Functions 


    public static function getSupportResistance($data, $index, $candleSpan)
    {
        $end = $index + 1; // +1 to include the $index item
        $length = 300;

        $start = max(0, $end - $length); // make sure we donâ€™t go negative
        $slicedData = array_slice($data, $start, $length);

        return MarketTrendService::getCurrentSupportResistanceValueFromData($slicedData, [$candleSpan])[$candleSpan];
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


    public static function getCoinReportsOnFormula($formula_id)
    {
        return  DB::table('coin_reports')
            ->join('formula_details', 'coin_reports.formula', '=', 'formula_details.formula')
            ->where('formula_details.id', $formula_id)
            ->select('coin_reports.*')
            ->get();
    }





    // #########################Functions for confirmed Trades table###############################

    public static function getIndexDiffFromTimestamps($timestamp1, $timestamp2, $interval, $rounded = true)
    {
        if (!($timestamp1 && $timestamp2)) {
            return false;
        }
        $intervalToMins = CommonHelpers::$binanceIntervals[$interval];
        $diff = abs($timestamp1 - $timestamp2) / (60 * 1000 * $intervalToMins);
        return $rounded ? intval($diff) : $diff;
    }


    public static function insertConfirmBasicTradeEntry($symbol, $position, $data, $index)
    {



        // BB Calculations for highest point squeez
        $highestPointIndex = self::getTightestSqueezIndex($data, $index);
        $bbDiffHighest = CommonHelpers::getPercentDiff($data[$highestPointIndex]['bb_lower'], $data[$highestPointIndex]['bb_upper']);



        $id =  DB::table('confirmed_trades')->insertGetId([
            'position' => $position,
            'coin_name' => $symbol,
            'confirm_candle_timestamp' => $data[$index]['binance_timestamp'],
            'candles_to_check' => self::$candlesToCheck,
            'trade_confirmed' => 0,
            'bolling_last_squeez_value' => $bbDiffHighest,
            'bolling_last_squeezed_timestamp' => $data[$highestPointIndex]['binance_timestamp'],
            'update_time' => Carbon::now()->toDateTimeString(),

        ]);
        return DB::table('confirmed_trades')->where('ict_id', $id)->first();
    }

    public static function getIctId($symbol, $position)
    {
        $lastEntry =  DB::table('confirmed_trades')->where('coin_name', $symbol)->where('position', $position)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }


    public static function checkConfirmTradeValidity($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol, $position);
        if (
            !$ictId
        ) {
            return null;
        }

        $lastEntry = DB::table('confirmed_trades')->where('ict_id', $ictId)->where('position', $position)->first();

        if (!$lastEntry) {
            return null;
        }
        $indexDiff = self::getIndexDiffFromTimestamps($data[$index]['binance_timestamp'], $lastEntry->confirm_candle_timestamp, self::$interval);
        if ($indexDiff > $lastEntry->candles_to_check) {
            DB::table('confirmed_trades')->where('ict_id', $ictId)->delete();
            return null;
        }
        return $lastEntry;
    }


    public static function confirmOpening($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol, $position);
        if (
            !$ictId
        ) {
            return null;
        }
        DB::table('confirmed_trades')->where('ict_id', $ictId)->delete();
        return true;
    }

    public static function getTightestSqueezIndex($data, $startIndex)
    {
        $minSqueeze = CommonHelpers::getPercentDiff(
            $data[$startIndex]['bb_lower'],
            $data[$startIndex]['bb_upper']
        );

        $tightestIndex = $startIndex;
        $currentIndex = $startIndex;

        // Step 1: Loop backward until histogram crosses from red to green
        while ($currentIndex > 0) {
            $currentSqueeze = CommonHelpers::getPercentDiff(
                $data[$currentIndex]['bb_lower'],
                $data[$currentIndex]['bb_upper']
            );

            if ($currentSqueeze < $minSqueeze) {
                $minSqueeze = $currentSqueeze;
                $tightestIndex = $currentIndex;
            }

            // Histogram crossover from red to green
            if (
                $data[$currentIndex]['histogram'] > 0 &&
                $data[$currentIndex - 1]['histogram'] < 0
            ) {
                break;
            }

            $currentIndex--;
        }

        // Step 2: After crossover, check previous 3-entry blocks for tighter squeeze
        while ($currentIndex > 2) {
            $foundSmaller = false;

            for ($i = 1; $i <= 3; $i++) {
                $checkIndex = $currentIndex - $i;
                if ($checkIndex < 0) break;

                $squeeze = CommonHelpers::getPercentDiff(
                    $data[$checkIndex]['bb_lower'],
                    $data[$checkIndex]['bb_upper']
                );

                if ($squeeze < $minSqueeze) {
                    $minSqueeze = $squeeze;
                    $tightestIndex = $checkIndex;
                    $currentIndex = $checkIndex; // Move back to this point
                    $foundSmaller = true;
                }
            }

            // If no tighter squeeze found in last 3, break
            if (!$foundSmaller) {
                break;
            }
        }

        return $tightestIndex;
    }


    public static function checkCurrentTrend($data, $index, $candlesToCheck = 350)
    {


        $maDecreasing = 0;
        $maIncreasing = 0;
        $maFlat = 0;

        if ($index <= $candlesToCheck) {
            $candlesToCheck = $index - 1;
        }
        // dd($index,$candlesToCheck);

        for ($i = $index; $i >= $index - $candlesToCheck; $i--) {
            if ($data[$i]['ma99'] > $data[$i - 1]['ma99']) {
                $maIncreasing++;
            } else if ($data[$i]['ma99'] < $data[$i - 1]['ma99']) {
                $maDecreasing++;
            } else {
                $maFlat++;
            }
        }

        if (
            $maIncreasing  < $maDecreasing
        ) {
            // dd($maIncreasing, $maDecreasing, $maFlat);
            return 'BERISH';
        } else if (
            $maIncreasing  > $maDecreasing
        ) {
            return 'BULLISH';
        } else {
            return 'FLAT';
        }
    }


    public static function checkTrendOnHigherCandles($symbol, $position, $data, $index, $higherInterval = '1h')
    {



        $dataHigher =  self::$activeExchange === 'binance' ?
            BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE', true)
            : HyperLiquidApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE', true);

        if (!$dataHigher) {
            return null;
        }
        $indexHigher = count($dataHigher) - 2;

        if ($position === 'LONG') {
            $loopIndex = $indexHigher;
            $crossOverCondition = false;
            $bbMiddleCondition = $dataHigher[$indexHigher]['bb_middle'] <= $dataHigher[$indexHigher - 1]['bb_middle'];

            // Check Last Crossover for dif dea
            while ($loopIndex > 0) {

                $difCurrent = $dataHigher[$loopIndex]['dif'];
                $deaCurrent = $dataHigher[$loopIndex]['dea'];

                $difPrev = $dataHigher[$loopIndex - 1]['dif'];
                $deaPrev = $dataHigher[$loopIndex - 1]['dea'];


                // Dif Crossing DEA from above
                if ($difCurrent < $deaCurrent && $difPrev >= $deaPrev) {
                    // if ($difCurrent > 0 && $deaCurrent > 0)
                    $crossOverCondition = true;
                    // else
                    // $crossOverCondition = false;
                    break;
                }
                // Dif Crossing DEA from below
                else if ($difCurrent > $deaCurrent && $difPrev <= $deaPrev) {
                    $crossOverCondition = false;
                    break;
                }

                $loopIndex--;
            }

            return !($crossOverCondition && $bbMiddleCondition);
        } else {
            $loopIndex = $indexHigher;
            $crossOverCondition = false;
            $bbMiddleCondition = $dataHigher[$indexHigher]['bb_middle'] >= $dataHigher[$indexHigher - 1]['bb_middle'];

            // Check Last Crossover for dif dea
            while ($loopIndex > 0) {

                $difCurrent = $dataHigher[$loopIndex]['dif'];
                $deaCurrent = $dataHigher[$loopIndex]['dea'];

                $difPrev = $dataHigher[$loopIndex - 1]['dif'];
                $deaPrev = $dataHigher[$loopIndex - 1]['dea'];


                // Dif Crossing DEA from above
                if ($difCurrent < $deaCurrent && $difPrev >= $deaPrev) {
                    // if ($difCurrent > 0 && $deaCurrent > 0)
                    $crossOverCondition = false;
                    // else
                    // $crossOverCondition = false;
                    break;
                }
                // Dif Crossing DEA from below
                else if ($difCurrent > $deaCurrent && $difPrev <= $deaPrev) {
                    $crossOverCondition = true;
                    break;
                }

                $loopIndex--;
            }

            return !(($crossOverCondition && $bbMiddleCondition));
        }
    }
    public static function detectLongEntryWithSR($data, $index, $srAnalysis = null)
    {
        // Safety check
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];
        $prev2 = $data[$index - 2];
        $prev3 = $data[$index - 3];

        // === SUPPORT/RESISTANCE ANALYSIS ===
        $srScore = 0;
        $srConfirmation = false;
        $suggestedSL = null;
        $suggestedTP = null;
        $riskReward = 0;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'buy') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    $suggestedSL = $signal['stop_loss'];
                    $suggestedTP = $signal['take_profit_1'];
                    $riskReward = $signal['risk_reward']['ratio'] ?? 0;
                    break;
                }
            }
        }

        // Analyze support levels for additional confirmation
        $nearSupport = false;
        $supportStrength = 0;
        $supportDistance = 999;

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'support') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $supportDistance = min($supportDistance, $distance);

                    // Check if price is near support (within 0.5%)
                    if ($distance <= 0.005) {
                        $nearSupport = true;
                        $supportStrength = $level['confidence'];

                        // Bonus points for high-volume support touches
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }

                        // Bonus for recent touches
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        // === TECHNICAL INDICATOR ANALYSIS ===

        // 1. Trend Analysis
        $trendScore = 0;

        // Moving Average Bullish Alignment
        if ($current['ma7'] > $current['ma14'] && $current['ma14'] > $current['ma25']) {
            $trendScore += 20;
        }

        // Price position relative to MAs
        if ($current['close'] > $current['ma14']) $trendScore += 10;
        if ($current['close'] > $current['ma25']) $trendScore += 10;

        // Bollinger Band position (near lower band suggests reversal)
        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition < 0.2) $trendScore += 15; // Near lower band
        if ($bbPosition < 0.1) $trendScore += 10; // Very close to lower band

        // 2. Momentum Analysis
        $momentumScore = 0;

        // RSI Analysis
        if ($current['rsi6'] < 30) $momentumScore += 20; // Oversold
        if ($current['rsi6'] < 35 && $current['rsi6'] > $prev1['rsi6']) $momentumScore += 15; // Turning up
        if ($current['rsi6'] > $prev1['rsi6'] && $current['close'] < $prev1['close']) $momentumScore += 10; // Bullish divergence

        // Stochastic Analysis
        if ($current['stoch_k'] < 20 && $current['stoch_d'] < 20) $momentumScore += 15;
        if ($current['stoch_k'] > $prev1['stoch_k'] && $current['stoch_d'] > $prev1['stoch_d']) $momentumScore += 10;

        // Williams %R
        if ($current['wr'] < -80) $momentumScore += 10; // Oversold

        // MACD Analysis
        if ($current['dif'] > $current['dea'] && $current['histogram'] > 0) $momentumScore += 10;
        if ($current['histogram'] > $prev1['histogram']) $momentumScore += 10; // Strengthening momentum

        // 3. Volume Analysis
        $volumeScore = 0;

        // Volume spike confirmation
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;

        // OBV bullish confirmation
        if ($current['obv'] > $prev1['obv']) $volumeScore += 10;
        if ($current['obv'] > $prev2['obv'] && $current['obv'] > $prev3['obv']) $volumeScore += 5;

        // Money Flow Index
        if ($current['mfi'] > 50 && $current['mfi'] > $prev1['mfi']) $volumeScore += 10;

        // 4. Price Action Analysis
        $priceActionScore = 0;

        // Bullish candlestick
        if ($current['close'] > $current['open']) $priceActionScore += 10;

        // Long lower wick (support/buying interest)
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $bodySize = abs($current['close'] - $current['open']);
        if ($lowerWick > $bodySize * 1.5) $priceActionScore += 15;

        // Failed breakdown pattern (bullish reversal)
        if ($current['low'] < $prev1['low'] && $current['close'] > $prev1['close']) $priceActionScore += 20;

        // Higher lows pattern
        if ($current['low'] > $prev1['low'] && $prev1['low'] > $prev2['low']) $priceActionScore += 10;

        // === ADVANCED FILTERS ===

        // Market structure confirmation
        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];

            // Support-heavy environment
            if ($structure['support_count'] > $structure['resistance_count']) {
                $structureScore += 10;
            }

            // Recent support interaction
            if (isset($structure['nearest_support']) && $supportDistance < 0.01) {
                $structureScore += 15;
            }
        }

        // === RISK MANAGEMENT CHECKS ===

        // Volatility filter
        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $highVolatility = $bbWidth > 0.08;

        // VWAP distance filter
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        // Recent strong bearish momentum check
        $recentBearMomentum = ($prev1['close'] < $prev2['close'] * 0.985) &&
            ($prev2['close'] < $prev3['close'] * 0.985);

        // === SCORING SYSTEM ===

        $totalTechnicalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;
        $totalScore = $totalTechnicalScore + ($srScore * 0.8); // Weight S/R analysis

        // === ENTRY CONDITIONS ===

        // Base requirements
        $baseConditionsMet = ($totalTechnicalScore >= 60) && // Strong technical setup
            ($current['close'] > $current['open']) && // Bullish candle
            !$highVolatility && // Reasonable volatility
            !$tooFarFromVWAP && // Near VWAP
            !$recentBearMomentum; // No strong counter-trend

        // Enhanced conditions with S/R
        $enhancedConditionsMet = $baseConditionsMet &&
            ($srConfirmation || $nearSupport) && // S/R confirmation
            ($srScore >= 60); // Minimum S/R confidence

        // === SPECIFIC ENTRY SIGNAL FOR 15M CANDLES ===
        // Target: 1% TP, 0.8% SL
        // RSI turning up from oversold + near support + strong S/R score

        if (
            $data[$index]['rsi6'] >= 30 &&
            $data[$index - 1]['rsi6'] <= 30 &&
            $data[$index]['rsi6'] > $data[$index - 1]['rsi6'] &&
            $nearSupport &&
            $srScore >= 75
        ) {
            return 'LONG';
        }

        return null;
    }
    public static function detectShortEntryWithSR($data, $index, $srAnalysis = null)
    {
        // Safety check
        if ($index < 3 || !isset($data[$index]) || !isset($data[$index - 1])) {
            return null;
        }

        $current = $data[$index];
        $prev1 = $data[$index - 1];
        $prev2 = $data[$index - 2];
        $prev3 = $data[$index - 3];

        // === SUPPORT/RESISTANCE ANALYSIS ===
        $srScore = 0;
        $srConfirmation = false;
        $suggestedSL = null;
        $suggestedTP = null;
        $riskReward = 0;

        if ($srAnalysis && isset($srAnalysis['trading_signals'])) {
            foreach ($srAnalysis['trading_signals'] as $signal) {
                if ($signal['type'] === 'sell') {
                    $srConfirmation = true;
                    $srScore = $signal['confidence'];
                    $suggestedSL = $signal['stop_loss'];
                    $suggestedTP = $signal['take_profit_1'];
                    $riskReward = $signal['risk_reward']['ratio'] ?? 0;
                    break;
                }
            }
        }

        // Analyze resistance levels for additional confirmation
        $nearResistance = false;
        $resistanceStrength = 0;
        $resistanceDistance = 999;

        if ($srAnalysis && isset($srAnalysis['support_resistance_levels'])) {
            foreach ($srAnalysis['support_resistance_levels'] as $level) {
                if ($level['type'] === 'resistance') {
                    $distance = abs($current['close'] - $level['avg_price']) / $current['close'];
                    $resistanceDistance = min($resistanceDistance, $distance);

                    // Check if price is near resistance (within 0.5%)
                    if ($distance <= 0.005) {
                        $nearResistance = true;
                        $resistanceStrength = $level['confidence'];

                        // Bonus points for high-volume resistance touches
                        if ($level['total_volume'] > 500000) {
                            $srScore += 10;
                        }

                        // Bonus for recent touches
                        if (isset($level['last_touch_index']) && ($index - $level['last_touch_index']) < 20) {
                            $srScore += 15;
                        }
                    }
                }
            }
        }

        // === TECHNICAL INDICATOR ANALYSIS ===

        // 1. Trend Analysis
        $trendScore = 0;

        // Moving Average Bearish Alignment
        if ($current['ma7'] < $current['ma14'] && $current['ma14'] < $current['ma25']) {
            $trendScore += 20;
        }

        // Price position relative to MAs
        if ($current['close'] < $current['ma14']) $trendScore += 10;
        if ($current['close'] < $current['ma25']) $trendScore += 10;

        // Bollinger Band position (near upper band suggests reversal)
        $bbPosition = ($current['close'] - $current['bb_lower']) / ($current['bb_upper'] - $current['bb_lower']);
        if ($bbPosition > 0.8) $trendScore += 15; // Near upper band
        if ($bbPosition > 0.9) $trendScore += 10; // Very close to upper band

        // 2. Momentum Analysis
        $momentumScore = 0;

        // RSI Analysis
        if ($current['rsi6'] > 70) $momentumScore += 20; // Overbought
        if ($current['rsi6'] > 65 && $current['rsi6'] < $prev1['rsi6']) $momentumScore += 15; // Turning down
        if ($current['rsi6'] < $prev1['rsi6'] && $current['close'] > $prev1['close']) $momentumScore += 10; // Bearish divergence

        // Stochastic Analysis
        if ($current['stoch_k'] > 80 && $current['stoch_d'] > 80) $momentumScore += 15;
        if ($current['stoch_k'] < $prev1['stoch_k'] && $current['stoch_d'] < $prev1['stoch_d']) $momentumScore += 10;

        // Williams %R
        if ($current['wr'] > -20) $momentumScore += 10; // Overbought

        // MACD Analysis
        if ($current['dif'] < $current['dea'] && $current['histogram'] < 0) $momentumScore += 10;
        if ($current['histogram'] < $prev1['histogram']) $momentumScore += 10; // Weakening momentum

        // 3. Volume Analysis
        $volumeScore = 0;

        // Volume spike confirmation
        if ($current['volume'] > $current['volumeMA5'] * 1.3) $volumeScore += 15;
        if ($current['volume'] > $current['volumeMA10'] * 1.2) $volumeScore += 10;

        // OBV bearish confirmation
        if ($current['obv'] < $prev1['obv']) $volumeScore += 10;
        if ($current['obv'] < $prev2['obv'] && $current['obv'] < $prev3['obv']) $volumeScore += 5;

        // Money Flow Index
        if ($current['mfi'] < 50 && $current['mfi'] < $prev1['mfi']) $volumeScore += 10;

        // 4. Price Action Analysis
        $priceActionScore = 0;

        // Bearish candlestick
        if ($current['close'] < $current['open']) $priceActionScore += 10;

        // Long upper wick (rejection)
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $bodySize = abs($current['close'] - $current['open']);
        if ($upperWick > $bodySize * 1.5) $priceActionScore += 15;

        // Failed breakout pattern
        if ($current['high'] > $prev1['high'] && $current['close'] < $prev1['close']) $priceActionScore += 20;

        // Lower highs pattern
        if ($current['high'] < $prev1['high'] && $prev1['high'] < $prev2['high']) $priceActionScore += 10;

        // === ADVANCED FILTERS ===

        // Market structure confirmation
        $structureScore = 0;
        if ($srAnalysis && isset($srAnalysis['market_structure'])) {
            $structure = $srAnalysis['market_structure'];

            // Resistance-heavy environment
            if ($structure['resistance_count'] > $structure['support_count']) {
                $structureScore += 10;
            }

            // Recent resistance interaction
            if (isset($structure['nearest_resistance']) && $resistanceDistance < 0.01) {
                $structureScore += 15;
            }
        }

        // === RISK MANAGEMENT CHECKS ===

        // Volatility filter
        $bbWidth = ($current['bb_upper'] - $current['bb_lower']) / $current['bb_middle'];
        $highVolatility = $bbWidth > 0.08;

        // VWAP distance filter
        $vwapDistance = abs($current['close'] - $current['vwap']) / $current['close'];
        $tooFarFromVWAP = $vwapDistance > 0.05;

        // ADX trend strength
        $weakTrend = $current['adx'] < 20;

        // Recent strong bullish momentum check
        $recentBullMomentum = ($prev1['close'] > $prev2['close'] * 1.015) &&
            ($prev2['close'] > $prev3['close'] * 1.015);

        // === SCORING SYSTEM ===

        $totalTechnicalScore = $trendScore + $momentumScore + $volumeScore + $priceActionScore + $structureScore;
        $totalScore = $totalTechnicalScore + ($srScore * 0.8); // Weight S/R analysis

        // === ENTRY CONDITIONS ===

        // Base requirements
        $baseConditionsMet = ($totalTechnicalScore >= 60) && // Strong technical setup
            ($current['close'] < $current['open']) && // Bearish candle
            !$highVolatility && // Reasonable volatility
            !$tooFarFromVWAP && // Near VWAP
            !$recentBullMomentum; // No strong counter-trend

        // Enhanced conditions with S/R
        $enhancedConditionsMet = $baseConditionsMet &&
            ($srConfirmation || $nearResistance) && // S/R confirmation
            ($srScore >= 60); // Minimum S/R confidence

        // Premium conditions (highest accuracy)
        $premiumConditionsMet = $enhancedConditionsMet &&
            ($resistanceStrength >= 80) && // Strong resistance
            ($totalScore >= 100) && // High combined score
            ($riskReward >= 1.5); // Good risk/reward

        // === RETURN SIGNAL ===

        if ($data[$index]['rsi6'] <= 65 && $data[$index - 1]['rsi6'] >= 65 && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] &&  $nearResistance && $srScore >= 70) {
            return 'SHORT';
        }
        return null;

        if ($premiumConditionsMet) {
            return [
                'signal' => 'SHORT',
                'confidence' => min(95, $totalScore),
                'entry_price' => $current['close'],
                'stop_loss' => $suggestedSL ?? ($current['high'] * 1.005),
                'take_profit_1' => $suggestedTP ?? ($current['close'] * 0.98),
                'take_profit_2' => $current['close'] * 0.96,
                'risk_reward' => $riskReward,
                'analysis' => [
                    'technical_score' => $totalTechnicalScore,
                    'sr_score' => $srScore,
                    'total_score' => $totalScore,
                    'near_resistance' => $nearResistance,
                    'resistance_strength' => $resistanceStrength,
                    'conditions_met' => 'premium'
                ]
            ];
        } elseif ($enhancedConditionsMet) {
            return [
                'signal' => 'SHORT',
                'confidence' => min(85, $totalScore * 0.9),
                'entry_price' => $current['close'],
                'stop_loss' => $suggestedSL ?? ($current['high'] * 1.003),
                'take_profit_1' => $suggestedTP ?? ($current['close'] * 0.985),
                'take_profit_2' => $current['close'] * 0.97,
                'risk_reward' => $riskReward,
                'analysis' => [
                    'technical_score' => $totalTechnicalScore,
                    'sr_score' => $srScore,
                    'total_score' => $totalScore,
                    'near_resistance' => $nearResistance,
                    'resistance_strength' => $resistanceStrength,
                    'conditions_met' => 'enhanced'
                ]
            ];
        }

        return null;
    }



    public static function checkConditionSetLongSR($symbol, $data, $index)
    {

        $accuracyStatsSR = self::getAccuracy('LONG', 'Base Report','SR');
        if ($accuracyStatsSR['accuracy'] < 75) {
            Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $symbol);
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectLongEntryWithSR($data, $index, $srAnalysis);


        if ($entry === 'LONG') {
            return $entry;
        }

        return null;
    }


    public static function checkConditionSetLongMACD($symbol, $data, $index)
    {

        $accuracyStatsMACD = self::getAccuracy('LONG', 'Base Report','MACD');;
        if ($accuracyStatsMACD['accuracy'] < 73) {
            Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $symbol);
            return null;
        }

        if (
            $data[$index]['histogram'] > $data[$index - 1]['histogram'] && $data[$index]['histogram'] < 0

            && $data[$index - 1]['histogram'] < $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] < 0
            && $data[$index - 2]['histogram'] < $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] < 0
            && $data[$index - 3]['histogram'] < $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] < 0
            && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)
        ) {
            self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $buyCondition =
                (
                    $data[$index]['rsi6'] < 30
                    && $data[$index]['rsi6'] > $data[$index - 1]['rsi6']
                    && $bbAnalysis['price_action']['is_near_lower_band']
                    && $data[$index]['close'] > $data[$index]['bb_lower']
                    && $data[$index]['open'] < $data[$index]['bb_lower']
                );

            if ($buyCondition) {
                self::confirmOpening($symbol, 'LONG', $data, $index);
                return 'LONG';
            }
        }

        return null;
    }









    public static function checkConditionSetShortSR($symbol, $data, $index)
    {

       
        $accuracyStatsSR = self::getAccuracy('SHORT', 'Base Report','SR');
        if ($accuracyStatsSR['accuracy'] < 75) {
            Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $symbol);
            return null;
        }

        $srAnalyzer = new SupportResistanceAnalyzer($data, $index);
        $srAnalysis = $srAnalyzer->analyze();

        $entry = self::detectShortEntryWithSR($data, $index, $srAnalysis);

        if ($entry === 'SHORT')
            return $entry;

        return null;
    }


    public static function checkConditionSetShortMACD($symbol, $data, $index)
    {


        $accuracyStatsMACD = self::getAccuracy('SHORT', 'Base Report','MACD');
        if ($accuracyStatsMACD['accuracy'] < 73) {
            Log::info('TriggersThreadOrderBook: Canceled Due to SAFE Mode low accuracy: ' . $symbol);
            return null;
        }

        if (
            $data[$index]['histogram'] < $data[$index - 1]['histogram'] && $data[$index]['histogram'] > 0
            && $data[$index - 1]['histogram'] > $data[$index - 2]['histogram'] && $data[$index - 1]['histogram'] > 0
            && $data[$index - 2]['histogram'] > $data[$index - 3]['histogram'] && $data[$index - 2]['histogram'] > 0
            && $data[$index - 3]['histogram'] > $data[$index - 4]['histogram'] && $data[$index - 3]['histogram'] > 0

            && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)

        ) {
            self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $buyCondition =
                (
                    $data[$index]['rsi6'] > 70
                    && $data[$index]['rsi6'] < $data[$index - 1]['rsi6']
                    && $bbAnalysis['price_action']['is_near_upper_band']
                    && $data[$index]['close'] < $data[$index]['bb_upper']
                    && $data[$index]['open'] > $data[$index]['bb_upper']
                );

            if ($buyCondition) {
                self::confirmOpening($symbol, 'SHORT', $data, $index);

                return 'SHORT';
            }
        }

        return null;
    }


    public static function getAccuracy($position, $formula = 'Base Report', $tagName = null)
    {
        
        // Build URL
        $url = "https://reachoutfans.com/csrf-free/safe-mode-accuracy/{$position}/{$formula}";

        if ($tagName) {
            $url .= '/' . $tagName;
        }

        // Make HTTP GET request
        $response = Http::get($url);

        // Extract data or fail gracefully
        if ($response->successful() && isset($response->json()['data'])) {
            return $response->json()['data'];
        }

        // Optional: return fallback value or throw exception
        return null; // or throw new \Exception("Failed to get accuracy");
    }
}
