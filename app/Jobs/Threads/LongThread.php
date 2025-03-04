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

        // Wait for 20 sec for confirmation
        $openTrade = true;

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        $isWick = CommonHelpers::isCandleWick($data[count($data) - 1], 'upper', 5, $this->supportResistance[7]['resistance'], $this->tradeInstance->symbol);
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('FutureTraderLongEXP1: Skipped due to last order close time: ' . $symbol);
            }
        }
        if ($isWick) {
            $openTrade = false;

            MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening LONG Due to Wick formation ' . $symbol);
            Log::info('FutureTraderLongEXP1: Retreating Due to upper wick');
        }
        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }
        if ($openTrade) {
            $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
            if (!(isset($open_order['is_open']) && $open_order['is_open']))
                BinanceApiService::openMarketPositionLiveTrader($this->tradeInstance->symbol, $this->tradeInstance->buyPrice, $this->tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $this->tradeInstance->leverage, $this->tradeInstance->tradeAccount, $this->formula);


            $tradeLoop = true;
            // Proceed trade until the position is closed
            while ($tradeLoop) {
                $open_order = CommonHelpers::checkOpenOrder($symbol, $this->tradeInstance->position, 'FUTURE', $trade_acc);
                if (!(isset($open_order['is_open']) && $open_order['is_open']))
                    $tradeLoop = false;
                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;
                $tradeLoop = self::manageOpenOrder($this->tradeInstance, $open_order['order'], $supportResistance, $this->profitIncrementPercentage);
                CommonHelpers::delayS(1);
            }
        } else {
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
                    'currentSupport' => $supportResistance[7]['support'],
                    'currentResistance' => $supportResistance[7]['resistance'],
                    'targetProfit' => $targetProfit,
                ]);
                return false;
            } else {

                MailerService::sendSkipEmail($tradeInstance, 'Skipped closing LONG Due to Wick formation ' . $tradeInstance->symbol);
                Log::info('FutureTraderLongEXP1: Retreating Due to upper wick');
            }
        } else if ($currentProfit > $targetProfit) {

            DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([

                'stopLoss' =>  $currentCandle['close'],
                'previousPrice' => $currentCandle['close'],
                'currentPrice' => $currentCandle['close'],
                'currentSupport' => $supportResistance[7]['support'],
                'currentResistance' => $supportResistance[7]['resistance'],
                'currentProfit' => $currentProfit,
                'targetProfit' => $targetProfit + $profitIncrementPercentage,
            ]);
        } else {

            $lastOrderOpen = DB::table('live_trades_future_results')->where('position', 'LONG')->where('trade_acc', $tradeInstance->tradeAccount)->where('symbol', $tradeInstance->symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();

            if ($lastOrderOpen) {
                $lastOrderOpen = $lastOrderOpen->created_at;
                $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderOpen);
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
                            'currentSupport' => $supportResistance[7]['support'],
                            'currentResistance' => $supportResistance[7]['resistance'],
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
                'currentSupport' => $supportResistance[7]['support'],
                'currentResistance' => $supportResistance[7]['resistance'],
            ]);
        }

        return true;
    }
}
