<?php

namespace App\Jobs\ThreadsOrderBook;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\HyperLiquidApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use App\Services\OpeningConditionServiceLive;
use App\Services\SupportResistanceAnalyzer;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
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
use Throwable;

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
    public $stopLoss = 1;
    public $nextSLTriggerTime = 30;
    public $slTriggerTimeInc = 30;
    public $targetProfit = 0.7;
    public $tpTriggerPoint = 0.5;
    public $profitIncrementPercentage = 0.2;
    public $profitIncrementPercentageNext = 0.1;
    public static $stopLossMarginPercentage = 0.1;
    public $formula = 'Pivot Swings';

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


                            // =========================== DECISION BLOCK ===============================

                            $openingResults = new OpeningConditionServiceLive($this->workerId, $this->account, self::$activeExchange);

                            // $opening15m = $openingResults->getOpeningOn15m($symbol);
                            $opening15m = $openingResults->getOpeningOn15m($symbol);



                            if ($opening15m['direction']) {
                                $tradeType = $opening15m['direction'];
                                $this->formula  = $opening15m['formula'];
                                $this->targetProfit  = $opening15m['targetProfit'];
                                $this->stopLoss  = $opening15m['stopLoss'];
                                $this->profitIncrementPercentage  = $opening15m['profitIncrementPercentage'];
                            } else {
                                CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                                $this->formula  = 'Pivot Swing';
                                continue;
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


                $openTrade = true;
                $trade_acc = $tradeToOpen->tradeAccount;
                $symbol = $tradeToOpen->symbol;
                $tradeInstance = $tradeToOpen;


                $currentOpenOrders = 0;

                if (self::$isSpot && $tradeType === 'LONG')
                    $currentOpenOrders = DB::table('live_trades_spot_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
                else
                    $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();

                // Condition to limit open orders for a symbol in long or short
                if ($currentOpenOrders >= 1) {

                    $openOrder = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->first();

                    $this->manageRestartedWorker($openOrder->orderId);


                    $openTrade = false;
                }

                Log::info("No open orders found, progressing to open... " . $symbol);



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




                        // Opening Confirmed till now - Checking for extreme price reversal
                        $priceBuffer = 0.1;

                        $previousPrice =  self::$activeExchange === 'binance' ?
                            BinanceApiService::getCurrentPrice($symbol, 'FUTURE')
                            : HyperLiquidApiService::getCurrentPrice($symbol, 'FUTURE');

                        while (true) {
                            $currentPrice = self::$activeExchange === 'binance' ?
                                BinanceApiService::getCurrentPrice($symbol, 'FUTURE')
                                : HyperLiquidApiService::getCurrentPrice($symbol, 'FUTURE');

                            if ($tradeType === 'LONG') {
                                if ($currentPrice > ($previousPrice * (1 + $priceBuffer / 100))) {
                                    break;
                                } else if ($currentPrice < $previousPrice) {
                                    $previousPrice = $currentPrice;
                                }
                            } else if ($tradeType === 'SHORT') {
                                if ($currentPrice < ($previousPrice * (1 - $priceBuffer / 100))) {
                                    break;
                                } else if ($currentPrice > $previousPrice) {
                                    $previousPrice = $currentPrice;
                                }
                            }
                            sleep(1);
                        }




                        $supportResistanceArr = [
                            'support' => 1,
                            'resistance' => 1,
                        ];
                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Opening Position: ' . $symbol);

                        // Checking if its not engaged in any other worker

                        Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Checking Worker Engagement: ' . $symbol);

                        $workerEngagement = CommonHelpers::checkWorkerEngagement($this->workerId, $symbol, $this->account);
                        if ($workerEngagement) {
                            CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                            Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Symbol: ' . $symbol . ' Already engaged in worker: ' . $workerEngagement->worker_id);
                            continue;
                        }


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

        $targetProfit = $open_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $thirdLastCandle = $candleData[count($candleData) - 3];
        $stopLoss = $open_order['stopLoss'];
        $index = count($candleData) - 2;
        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': ' . $open_order['symbol'] . ' ' . $open_order['position'] . ' Current profit ' . $currentProfit);



        // Handle Early Closing on Order Books

        $closeEarly = false;

        // // Early Closing Logic
        // $openTimestamp = $open_order['created_at'];
        // $minsPast = abs(Carbon::now('Asia/Karachi')->diffInMinutes($openTimestamp));


        // if ($minsPast <= 15) {
        //     if (
        //         $candleData[$index]['close'] < $candleData[$index]['bb_lower']
        //         && $candleData[$index - 1]['close'] < $candleData[$index - 1]['bb_lower']
        //     ) {
        //         $closeEarly = true;
        //     }
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

                // Extract Meta values
                $currentPrice = $currentCandle['close'];
                $openingPrice = $open_order['price'];


                $newTakeProfitPercentage = $targetProfit + $profitIncrementPercentage;

                $takeProfitPrice = $openingPrice * (1 + $newTakeProfitPercentage / 100);
                $stopLossPrice = $currentPrice * (1 - $profitIncrementPercentage / 100);


                // DISABLED TEMPORARILY
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
                'stopLoss' =>  $stopLossPrice,
                'previousPrice' => $currentPrice,
                'currentPrice' => $currentPrice,
                'currentProfit' => $currentProfit,
                'targetProfit' => $newTakeProfitPercentage,
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
        $targetProfit = $open_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $thirdLastCandle = $candleData[count($candleData) - 3];

        $index = count($candleData) - 2;
        $stopLoss = $open_order['stopLoss'];


        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100 * -1;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': ' . $open_order['symbol'] . ' ' . $open_order['position'] . ' Current profit ' . $currentProfit);



        $closeEarly = false;
        // Early Closing Logic
        $openTimestamp = $open_order['created_at'];
        $minsPast = abs(Carbon::now('Asia/Karachi')->diffInMinutes($openTimestamp));


        if ($minsPast <= 15) {
            if (
                $candleData[$index]['close'] > $candleData[$index]['bb_upper']
                && $candleData[$index - 1]['close'] > $candleData[$index - 1]['bb_upper']
            ) {
                $closeEarly = true;
            }
        }


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

                // Extract Meta values
                $currentPrice = $currentCandle['close'];
                $openingPrice = $open_order['price'];


                $newTakeProfitPercentage = $targetProfit + $profitIncrementPercentage;
                $takeProfitPrice = $openingPrice * (1 - $newTakeProfitPercentage / 100);

                $stopLossPrice = $currentPrice * (1 + $profitIncrementPercentage / 100);

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
                'stopLoss' =>  $stopLossPrice,
                'previousPrice' => $currentPrice,
                'currentPrice' => $currentPrice,
                'currentProfit' => $currentProfit,
                'targetProfit' => $newTakeProfitPercentage,
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
}
