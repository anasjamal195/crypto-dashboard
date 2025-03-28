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

class TriggersThread implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $workerId;
    public $timeout = 360000000;
    public $tries = 1; // The job will only run once
    public $stopLoss = 1.5;
    public $targetProfit = 0.5;

    public $tradeInstance;
    public $supportResistance;
    public $formula = 'Order Book Snapshots ';
    public $profitIncrementPercentage = 0.2;

    public $triggerPrice = 0;
    public $triggerIndex = 0;


    /**
     * Create a new job instance.
     */
    public function __construct($workerId)
    {
        $this->workerId = $workerId;
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
                            $tradeInstance = DB::table('trade_handler')->where('id', $worker_symbol->trade_handler_id)->first();
                            $trigger = DB::table('order_book_snapshots')->where('id', $worker_symbol->trigger_id)->first();

                            $symbol = $tradeInstance->symbol;
                            $trade_acc = $tradeInstance->tradeAccount;


                            $data = BinanceApiService::getCandleStickData($tradeInstance->symbol, '5m', 300, null, 'FUTURE');
                            $index = count($data) - 1;
                            $index--;


                            $candle = $data[count($data) - 1];
                            $secondLastcandle = $data[count($data) - 2];
                            $thirdLastcandle = $data[count($data) - 3];

                            $resistanceLevels = array_map(function ($level) {
                                return $level['price'];
                            }, json_decode($trigger->resistance_levels,true));

                            $supportLevels = array_map(function ($level) {
                                return $level['price'];
                            }, json_decode($trigger->support_levels,true));


                            $triggerPriceShort = min($resistanceLevels);
                            $triggerPricePercentDiffShort = round(CommonHelpers::getPercentDiff($data[$index - 1]['close'], $triggerPriceShort), 2);
                            $triggerPriceLong = max($supportLevels);
                            $triggerPricePercentDiffLong =  round(CommonHelpers::getPercentDiff($data[$index - 1]['close'], $triggerPriceLong), 2);


                            if ($triggerPricePercentDiffShort >=  2 * $triggerPricePercentDiffLong) {
                                $tradeInstance = DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('position', 'LONG')->where('interval', '5m')->first();
                                $tradeType = 'LONG';
                                $triggerPrice = $triggerPriceShort;
                            } else if ($triggerPricePercentDiffLong >=  2 * $triggerPricePercentDiffShort) {
                                $tradeInstance = DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('position', 'SHORT')->where('interval', '5m')->first();
                                $triggerPrice = $triggerPriceLong;
                                $tradeType = 'SHORT';
                            } else {
                                continue;
                            }

                            $currentWorkerSymbols = DB::table('worker_symbols')->where('worker_id', $this->workerId)->pluck('symbol');

                            if ($tradeType == 'SHORT') {

                                if ($data[$index - 1]['high'] >= $triggerPriceLong  && $data[$index]['per'] < 0) {

                                    // If price hits trigger than pass current tradeInstance to parent function 
                                    $tradeToOpen =  $tradeInstance;
                                    // Free all other coins from this worker
                                    DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', '!=', $symbol)->delete();
                                    DB::table('workers')->where('worker_id', $this->workerId)->update([
                                        'symbol_count' => 1,
                                        'trade_status' => true,
                                        'active_status' => true,
                                    ]);
                                    DB::table('trade_handler')->whereIn('symbol', $currentWorkerSymbols)->where('symbol', '!=', $symbol)->where('tradeAccount', $tradeInstance->tradeAccount)->update([
                                        'isWorkerDispatched' => false,
                                    ]);


                                    // break current for loop for next processing
                                    break;
                                } else if (CommonHelpers::getPercentDiff($candle['close'], $triggerPriceLong) >  1) {

                                    // In case trigger fails or does not hit, remove the entry from worker_symbols

                                    // Free this coin from this worker
                                    DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', $symbol)->delete();
                                    DB::table('workers')->where('worker_id', $this->workerId)->update([
                                        'symbol_count' => count($currentWorkerSymbols) - 1,
                                        'trade_status' => false,
                                        'active_status' => true,
                                    ]);
                                    DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                                        'isWorkerDispatched' => false,
                                    ]);
                                }
                            } else if ($tradeType == 'LONG') {

                                if ($data[$index - 1]['low'] <= $triggerPriceShort  && $data[$index]['per'] > 0) {

                                    // If price hits trigger than pass current tradeInstance to parent function 
                                    $tradeToOpen =  $tradeInstance;
                                    // Free all other coins from this worker
                                    DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', '!=', $symbol)->delete();
                                    DB::table('workers')->where('worker_id', $this->workerId)->update([
                                        'symbol_count' => 1,
                                        'trade_status' => true,
                                        'active_status' => true,
                                    ]);
                                    DB::table('trade_handler')->whereIn('symbol', $currentWorkerSymbols)->where('symbol', '!=', $symbol)->where('tradeAccount', $tradeInstance->tradeAccount)->update([
                                        'isWorkerDispatched' => false,
                                    ]);


                                    // break current for loop for next processing
                                    break;
                                } else if (CommonHelpers::getPercentDiff($candle['close'], $triggerPriceShort) >  1) {

                                    // In case trigger fails or does not hit, remove the entry from worker_symbols

                                    // Free this coin from this worker
                                    DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', $symbol)->delete();
                                    DB::table('workers')->where('worker_id', $this->workerId)->update([
                                        'symbol_count' => count($currentWorkerSymbols) - 1,
                                        'trade_status' => false,
                                        'active_status' => true,
                                    ]);

                                    DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->update([
                                        'isWorkerDispatched' => false,
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('TriggersThreadOrderBook: Error - ' . $e->getMessage());
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


                $lastOrderClose = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
                $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
                if ($lastOrderClose) {
                    $lastOrderClose = $lastOrderClose->created_at;
                    $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
                    if ($timeDiff < 20) {
                        $openTrade = false;
                        Log::info('TriggersThreadOrderBook: Skipped due to last order close time: ' . $symbol);
                    }
                }


                // Condition to limit open orders for a symbol in long or short
                if ($currentOpenOrders >= 1) {
                    $openTrade = false;
                }



                if ($openTrade) {

                    $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);
                    if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                        $supportResistanceArr = [
                            'support' => 1,
                            'resistance' => 1,
                        ];
                        Log::info('TriggersThreadOrderBook: Opening Position: ' . $symbol);
                        
                        BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount, $this->formula, $supportResistanceArr, 0, false, $this->stopLoss, $this->targetProfit);

                        $tradeLoop = true;
                        // Proceed trade until the position is closed
                        while ($tradeLoop) {
                            try {
                                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, 'FUTURE', $trade_acc);
                                if (!(isset($open_order['is_open']) && $open_order['is_open']))
                                    $tradeLoop = false;
                                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                                if ($tradeType === 'LONG')
                                    $tradeLoop = self::manageOpenOrderLong($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                                else if ($tradeType === 'SHORT')
                                    $tradeLoop = self::manageOpenOrderShort($tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                            } catch (\Exception $e) {
                                Log::error('TriggersThreadOrderBook: Error - ' . $e->getMessage());
                                Log::error($e->getTraceAsString());
                            }
                            CommonHelpers::delayS(1);
                        }

                        // Trade Completion, Remove and free this coin from this worker and prepare for next iteration
                        DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', $symbol)->delete();
                        DB::table('workers')->where('worker_id', $this->workerId)->update([
                            'symbol_count' => 0,
                            'trade_status' => false,
                            'active_status' => true,
                        ]);
                        DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->update([
                            'isWorkerDispatched' => false,
                        ]);

                        Log::info('TriggersThreadOrderBook: Trade Successfully Closed: ' . $symbol);
                    }
                } else {
                    // Trade Failure
                    DB::table('worker_symbols')->where('worker_id', $this->workerId)->where('symbol', $symbol)->delete();
                    DB::table('workers')->where('worker_id', $this->workerId)->update([
                        'symbol_count' => 0,
                        'trade_status' => false,
                        'active_status' => true,
                    ]);
                    DB::table('trade_handler')->where('symbol', $symbol)->where('tradeAccount', $trade_acc)->where('interval', '5m')->update([
                        'isWorkerDispatched' => false,
                    ]);

                    Log::info('TriggersThreadOrderBook: Failed to open trade: ' . $symbol);
                }


                // Recall this loop after every successful trade
                sleep(5);
            } catch (\Exception $e) {
                Log::error('TriggersThreadOrderBook: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        }
    }

    private static function manageOpenOrderLong($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {

        Log::info('TriggersThreadOrderBook: Open order found for ' . $buy_order['symbol']);
        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];
        $stopLoss = $buy_order['stopLoss'];
        $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;


        // Scenerio 1: If Current profit is less than 1%
        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100;
        Log::info('TriggersThreadOrderBook: Current profit ' . $currentProfit);



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
                Log::info('TriggersThreadOrderBook: Retreating Due to upper wick');
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

            DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit
            ]);
        }

        return true;
    }


    private static function manageOpenOrderShort($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {
        Log::info('ShortThreadOrderBook: Open order found for ' . $buy_order['symbol']);

        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];

        $stopLoss = $buy_order['stopLoss'];



        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
        Log::info('ShortThreadOrderBook: Current profit ' . $currentProfit);

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
                Log::info('ShortThreadOrderBook: Retreating Due to lower wick');
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
