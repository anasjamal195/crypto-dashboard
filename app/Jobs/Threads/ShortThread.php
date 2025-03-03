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

        $lastOrderClose = DB::table('live_trades_future_results')->where('position', 'SHORT')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'close')->orderBy('created_at', 'desc')->first();
        $isWick = CommonHelpers::isCandleWick($data[count($data) - 1], 'lower', 5, $this->supportResistance[7]['support'], $this->tradeInstance->symbol);
        $currentOpenOrders = DB::table('live_trades_future_results')->where('trade_acc', $trade_acc)->where('symbol', $symbol)->where('trade_status', 'open')->count();
        if ($lastOrderClose) {
            $lastOrderClose = $lastOrderClose->created_at;
            $timeDiff = Carbon::now('Asia/Karachi')->diffInMinutes($lastOrderClose);
            if ($timeDiff < 20) {
                $openTrade = false;
                Log::info('FutureTraderShortEXP1: Skipped due to last order close time: ' . $symbol);
            }
        }
        if ($isWick) {
            $openTrade = false;

            MailerService::sendSkipEmail($this->tradeInstance, 'Skipped opening SHORT Due to Wick formation ' . $symbol);
            Log::info('FutureTraderShortEXP1: Retreating Due to upper wick');
        }
        // Condition to limit open orders for a symbol in long or short
        if ($currentOpenOrders >= 1) {
            $openTrade = false;
        }
        if ($openTrade) {
            $openOrder = BinanceApiService::openMarketPositionLiveTrader($this->tradeInstance->symbol, $this->tradeInstance->buyPrice, $this->tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $this->tradeInstance->leverage, $this->tradeInstance->tradeAccount, $this->formula);


            $tradeLoop = true;
            // Proceed trade until the position is closed
            while ($tradeLoop) {

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', 'FUTURE', [7]);
                $candleData = $supportResistance['candleData'];
                $isCandleClosing = (now()->timestamp - $candleData[count($candleData) - 1]['binance_timestamp'] / 1000) <= 40;

                $tradeLoop = self::manageOpenOrder($this->tradeInstance, $openOrder['order'], $supportResistance, $this->profitIncrementPercentage);

                CommonHelpers::delayS(1);
            }
        } else {
            Log::info('FutureTraderShortEXP1: Failed to open trade: ' . $symbol);
        }
    }

    private static function manageOpenOrder($tradeInstance,  $buy_order, $supportResistance, $profitIncrementPercentage)
    {
        Log::info('FutureTraderShortEXP1: Open order found for ' . $buy_order['symbol']);

        $targetProfit = $buy_order['targetProfit'];
        $candleData = $supportResistance['candleData'];
        $currentCandle = $candleData[count($candleData) - 1];

        $stopLoss = $buy_order['stopLoss'];



        $currentProfit = (($currentCandle['close'] - $buy_order['price']) / $buy_order['price']) * 100 * -1;
        Log::info('FutureTraderShortEXP1: Current profit ' . $currentProfit);

        if ($currentCandle['close'] > $stopLoss) {
            $upper_wick = CommonHelpers::isCandleWick($currentCandle, 'upper', 5, $stopLoss, $tradeInstance->symbol);
            if (!$upper_wick) {
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
            } else {
                Log::info('FutureTraderShortEXP1: Retreating Due to lower wick');
                MailerService::sendSkipEmail($tradeInstance, 'Skipped closing SHORT Due to Wick formation ' . $tradeInstance->symbol);
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
