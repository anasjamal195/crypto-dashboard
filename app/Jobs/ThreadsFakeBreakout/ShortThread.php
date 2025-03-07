<?php

namespace App\Jobs\ThreadsFakeBreakout;

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
        $this->formula = 'Support/Resistance Fake Breakout Multithread';
        $this->profitIncrementPercentage = 0.2;
    }


    public function handle(): void
    {
        $data = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '5m', 300, null, 'FUTURE');
        $symbol = $this->tradeInstance->symbol;
        $trade_acc = $this->tradeInstance->tradeAccount;

        // Wait for 20 sec for confirmation
        $openTrade = true;

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose));
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('ShortThread: Skipped due to last order close time: ' . $symbol);
            }
        }

        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }

        $fakeBreakoutLoop = true;
        $counter = 0;
        while ($fakeBreakoutLoop) {
            $data1m = BinanceApiService::getCandleStickData($this->tradeInstance->symbol, '1m', 5, null, 'FUTURE');
            $candle1m = $data1m[count($data1m) - 1];
            $secondLastcandle1m = $data1m[count($data1m) - 2];
            $thirdLastcandle1m = $data1m[count($data1m) - 3];
            Log::info('ShortThread::Checking Candle Direction on 1m ' . $symbol);

            if ($candle1m['open'] > $candle1m['close'] && $secondLastcandle1m['open'] > $secondLastcandle1m['close']) {
                Log::info('ShortThread::Candle Direction True on 1m ' . $symbol);

                $fakeBreakoutLoop = false;
            } else if ($counter >= 240) {
                Log::info('ShortThread::Candle Direction Timeout, No signal found ' . $symbol);

                $fakeBreakoutLoop = false;
                $openTrade = false;
            }

            $counter++;
            CommonHelpers::delayS(1);
        }



        if ($openTrade) {
            Log::info('ShortThread::Going to open trade '. $symbol);

            Cache::put($symbol . '_availability', 0, now()->addMinute());

            $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
            if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                $supportResistanceArr = [
                    'support' => $this->supportResistance[7]['support'],
                    'resistance' => $this->supportResistance[7]['resistance'],
                ];
                BinanceApiService::openMarketPositionLiveTrader($this->tradeInstance->symbol, $this->tradeInstance->buyPrice, $this->tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $this->tradeInstance->leverage, $this->tradeInstance->tradeAccount, $this->formula, $supportResistanceArr);
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
                    Log::error('ShortThread: Error - ' . $e->getMessage());
                    Log::error($e->getTraceAsString());
                }
                CommonHelpers::delayS(1);
            }
        } else {
            Cache::put($symbol . '_availability', 1, now()->addMinute());

            Log::info('ShortThread: Failed to open trade: ' . $symbol);
        }
    }

    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {
        Log::info('ShortThread: Open order found for ' . $buy_order['symbol']);

        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];
        $secondLastCandle = $candleData[count($candleData) - 2];

        $stopLoss = $buy_order['stopLoss'];



        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
        Log::info('ShortThread: Current profit ' . $currentProfit);

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
                return false;
            } else {
                Log::info('ShortThread: Retreating Due to lower wick');
                MailerService::sendSkipEmail($tradeInstance, 'Skipped closing SHORT Due to Wick formation ' . $tradeInstance->symbol);
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

            if ($lastOrderOpen) {
                $lastOrderOpen = $lastOrderOpen->created_at;
                $timeDiff = abs(Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderOpen));
                if ($timeDiff > 5 && $timeDiff < 10) {
                    $diff = $secondLastCandle['open'] - $secondLastCandle['close'];
                    if (
                        $currentCandle['close'] > $currentCandle['open']  && $currentCandle['close'] >= (min($secondLastCandle['close'], $secondLastCandle['open']) + ($diff * 0.6))
                        && $currentCandle['rsi6'] > $secondLastCandle['rsi6'] * 1.25
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
                'targetProfit' => $targetProfit,

            ]);
        }

        return true;
    }
}
