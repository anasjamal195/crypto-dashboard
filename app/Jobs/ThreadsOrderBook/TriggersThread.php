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
    public $stopLoss = 0.8;
    public $nextSLTriggerTime = 30;
    public $slTriggerTimeInc = 30;
    public $targetProfit = 0.5;
    public $profitIncrementPercentage = 0.2;
    public $profitIncrementPercentageNext = 0.1;
    public $stopLossMarginPercentage = 0.1;
    public $formula = 'MACD & SR';

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

                            $opening15m = $openingResults->getOpeningOn15m($symbol);
                            $opening5m = $openingResults->getOpeningOn5m($symbol);



                            if ($opening5m['direction']) {
                                $tradeType = $opening5m['direction'];
                                $this->formula  = $opening5m['formula'];
                            } else if ($opening15m['direction']) {
                                $tradeType = $opening15m['direction'];
                                $this->formula  = $opening15m['formula'];
                            } else {
                                CommonHelpers::workerFreeSymbol($this->workerId, $symbol, $this->account);
                                $this->formula  = 'MACD & SR';
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

        $targetProfit = $open_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $thirdLastCandle = $candleData[count($candleData) - 3];
        $stopLoss = $open_order['stopLoss'];
        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': ' . $open_order['symbol'] . ' ' . $open_order['position'] . ' Current profit ' . $currentProfit);



        // Handle Early Closing on Order Books

        $closeEarly = false;


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
                $stopLossPrice = $currentPrice * (1 - self::$stopLossMarginPercentage / 100);


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

        $stopLoss = $open_order['stopLoss'];


        $currentProfit = (($currentCandle['close'] - $open_order['price']) / $open_order['price']) * 100 * -1;
        Log::info('TriggersThreadOrderBook ' . $workerId . ': ' . $open_order['symbol'] . ' ' . $open_order['position'] . ' Current profit ' . $currentProfit);



        $closeEarly = false;


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

                $stopLossPrice = $currentPrice * (1 + self::$stopLossMarginPercentage / 100);

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


    public static function getAccuracy($position, $formula = 'Base Report', $tagName = null)
    {
        // Generate a unique cache key
        $cacheKey = "accuracy_{$position}_" . md5($formula . '_' . ($tagName ?? ''));

        // Attempt to get from cache first
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($position, $formula, $tagName) {
            try {
                // Build URL
                $url = "https://reachoutfans.com/csrf-free/safe-mode-accuracy/{$position}/{$formula}";

                if ($tagName) {
                    $url .= '/' . $tagName;
                }

                // Make HTTP GET request with timeout
                $response = Http::timeout(10)->get($url);

                if ($response->successful() && isset($response->json()['data'])) {
                    return $response->json()['data'];
                }

                // Log unexpected response
                Log::warning("getAccuracy: Unexpected response format", [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (RequestException $e) {
                Log::error("getAccuracy: HTTP request failed", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                Log::error("getAccuracy: Unexpected error", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            // Fallback if request fails or returns no valid data
            return ['accuracy' => 0];
        });
    }
}
