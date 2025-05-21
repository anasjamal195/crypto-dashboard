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
    public $supportResistanceCandleSpan = 3;
    public static $interval;
    public $supportResistance;
    public $triggerPrice = 0;
    public $triggerIndex = 0;
    public $workerId;
    public $account;



    // Meta data
    public $stopLoss = 1;
    public $nextSLTriggerTime = 30;
    public $slTriggerTimeInc = 30;
    public $targetProfit = 0.4;
    public $profitIncrementPercentage = 0.05;
    public $profitIncrementPercentageNext = 0.1;
    public $formula = 'RSI Swings with Bollinger Bands';

    // Confirmed Trades Entries

    public static $candlesToCheck = 1000;
    public static $volumeMA5ValidFor = 1000;
    public static $upperWickValidFor = 1000;
    public static $bollSqueezValidFor = 1000;


    /**
     * Create a new job instance.
     */
    public function __construct($workerId, $account)
    {
        $this->workerId = $workerId;
        $this->account = $account;
        self::$interval = CommonHelpers::getMetaValue($this->account, 'live_trade_worker_interval_future', '1m');
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

                            $data = BinanceApiService::getCandleStickData($symbol, self::$interval, 300, null, 'FUTURE');
                            $index = count($data) - 1;
                            // Decrement index to get last completed candle
                            $index--;
                            $supportResistance = MarketTrendService::getCurrentSupportResistanceValueFromData($data, [$this->supportResistanceCandleSpan]);


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

                            // ========================================================================




                            // ===========Initiate Open Trade Process==================================
                            $tradeInstance = CommonHelpers::getTradeHandler($symbol, $this->account, $tradeType, self::$interval);

                            if (!$tradeInstance) {
                                break;
                            }
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
                // $isCandleClosing = (now()->timestamp - $data[count($data) - 1]['binance_timestamp'] / 1000) <= 40;

                // if (!$isCandleClosing) {

                //     Log::info('TriggersThreadOrderBook ' . $this->workerId . ': Canceled Due to candle closing: ' . $symbol);

                //     $openTrade = false;
                // }

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


                                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, self::$interval, 'FUTURE', [$this->supportResistanceCandleSpan]);
                                if ($tradeType === 'LONG')
                                    $tradeLoop = $this->manageOpenOrderLong($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
                                else if ($tradeType === 'SHORT')
                                    $tradeLoop = $this->manageOpenOrderShort($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage, $this->workerId);
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
            // $this->nextSLTriggerTime = 30;
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
            // $this->nextSLTriggerTime = 30;
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








    // Function to check opening Conditions

    public static function handleOpeningConditionsLong($symbol, $data, $index)
    {

        // Long Conditions
        if ($data[$index]['rsi6'] < 30 && !self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {
            self::insertConfirmBasicTradeEntry($symbol, 'LONG', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'LONG', $data, $index)) {

            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $buyCondition = $data[$index]['close'] > $data[$index]['bb_lower']
                && $data[$index]['open'] < $data[$index]['bb_lower']
                && $data[$index]['stoch_d'] > $data[$index - 1]['stoch_d']
                && $data[$index]['stoch_k'] > $data[$index - 1]['stoch_k']
                && $bbAnalysis['price_action']['is_near_lower_band']
                && !$bbAnalysis['bb_squeeze']
                && $data[$index]['histogram'] > $data[$index - 1]['histogram'];



            if ($buyCondition) {
                self::confirmOpening($symbol, 'LONG', $data, $index);


                $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'LONG', $data, $index);

                if ($allowOnHigherTrend) {
                    return 'LONG';
                }
            }
        }


        // No conditions met so return null
        return null;
    }

    public static function handleOpeningConditionsShort($symbol, $data, $index)
    {
        if ($data[$index]['rsi6'] > 70 && !self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {
            self::insertConfirmBasicTradeEntry($symbol, 'SHORT', $data, $index);
        }

        if (self::checkConfirmTradeValidity($symbol, 'SHORT', $data, $index)) {

            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($data, $index, 10);
            $sellCondition =

                $data[$index]['close'] < $data[$index]['bb_upper']
                && $data[$index]['open'] > $data[$index]['bb_upper']
                && $data[$index]['stoch_d'] < $data[$index - 1]['stoch_d']
                && $data[$index]['stoch_k'] < $data[$index - 1]['stoch_k']
                && $bbAnalysis['price_action']['is_near_upper_band']
                && !$bbAnalysis['bb_squeeze']
                && $data[$index]['histogram'] < $data[$index - 1]['histogram']
                && $data[$index - 1]['stoch_d'] < 100
                && $data[$index - 1]['stoch_k'] < 100;


            if ($sellCondition) {
                self::confirmOpening($symbol, 'SHORT', $data, $index);
                $allowOnHigherTrend = self::checkTrendOnHigherCandles($symbol, 'SHORT', $data, $index, '30m');
                if ($allowOnHigherTrend) {
                    return 'SHORT';
                }
            }
        }


        // No conditions met so return null
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

    public static function getIctId($symbol)
    {
        $lastEntry =  DB::table('confirmed_trades')->where('coin_name', $symbol)->where('trade_confirmed', 0)->orderBy('update_time', 'DESC')->first();
        return $lastEntry ? $lastEntry->ict_id : null;
    }


    public static function checkConfirmTradeValidity($symbol, $position, $data, $index)
    {
        $ictId = self::getIctId($symbol);
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
            DB::table('confirmed_trades')->where('ict_id', $ictId)->update([
                'trade_confirmed' => 1,
                'update_time' => Carbon::now()->toDateTimeString(),
            ]);
            return null;
        }
        return $lastEntry;
    }


    public static function confirmOpening($symbol, $data, $index)
    {
        $ictId = self::getIctId($symbol);
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



        $dataHigher = BinanceApiService::getCandleStickDataPast($symbol, $higherInterval, 500, $data[$index]['binance_timestamp'], 'FUTURE');
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
}
