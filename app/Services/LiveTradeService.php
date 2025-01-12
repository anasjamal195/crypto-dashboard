<?php

namespace App\Services;

use App\CommonHelpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($interval, $market)
    {
        $tradeHandler = DB::table('trade_handler')->where('interval', $interval)->where('market', $market)->where('isActive', 1)->get();
        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $interval = $tradeInstance->interval;
                $trade_acc = $tradeInstance->tradeAccount;
                $buy_coin_price = $tradeInstance->buyPrice;
                $rsiThreshold = $tradeInstance->rsiThreshold;
                $targetProfit = $tradeInstance->targetProfit;
                $stopLossReductionPrecentage = $tradeInstance->stopLossReductionPrecentage;
                $obvLimit = $tradeInstance->obvLimit;
                $stochLimit = $tradeInstance->stochLimit;
                $priceLockBuffer = $tradeInstance->priceLockBuffer;
                $leverage = $tradeInstance->leverage;
                $candleData = BinanceApiService::getCandleStickData($symbol, $interval, 1000, null, $market);
                Log::info('AutoTraderSpot: Current Trade');
                Log::info('AutoTraderSpot: Coin: ' . $symbol);
                Log::info('AutoTraderSpot: Interval: ' . $interval);
                Log::info('AutoTraderSpot: Account: ' . $trade_acc);
                Log::info('AutoTraderSpot: Invested: ' . $buy_coin_price . ' $');
                Log::info('AutoTraderSpot: RSI Threshold: ' . $rsiThreshold);
                Log::info('AutoTraderSpot: OBV Limit: ' . $obvLimit);
                Log::info('AutoTraderSpot: Price Lock Buffer: ' . $priceLockBuffer);


                $open_order = CommonHelpers::checkOpenOrder($symbol, $interval, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($open_order['order'], $candleData, $targetProfit, $stopLossReductionPrecentage, $market);
                } else {
                    $secondLastCandle = $candleData[count($candleData) - 2];

                    if ($tradeInstance->priceLock != 0) {
                        self::managePriceLock($tradeInstance);
                    } else if ($secondLastCandle['rsi6'] <= $rsiThreshold && ($secondLastCandle['ma7'] < $secondLastCandle['ma25'] || $secondLastCandle['ma25'] < $secondLastCandle['ma99'])) {
                        $index = 99;
                        $previousHighObv = 0;
                        for ($i = $index - 15; $i <= $index; $i++) {
                            if ($candleData[$i]['obv'] > $previousHighObv) {
                                $previousHighObv = $candleData[$i]['obv'];
                            }
                        }

                        $stochCondition =   ($secondLastCandle['stoch_d'] <=  $stochLimit);
                        $obvCondition = ($secondLastCandle['obv'] <= ($previousHighObv * (1 - $obvLimit / 100)));

                        $obvPositiveCondition = true;
                        $difCondition = true;
                        if ($secondLastCandle['obv'] > 0 && $secondLastCandle['rsi6'] > 0) {
                            $obvPositiveCondition = false;
                        }
                        // if ($secondLastCandle['dif'] >= 0) {
                        //     $difCondition = false;
                        // }
                        // dd($obvCondition,$stochCondition,$obvPositiveCondition,$difCondition);
                        if (
                            $obvCondition &&
                            $stochCondition &&
                            $obvPositiveCondition &&
                            $difCondition
                        ) {
                            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                                'priceLock' => BinanceApiService::getCurrentPrice($symbol, $market) * (1 + $priceLockBuffer / 100),
                            ]);
                        }
                    }
                }
                usleep(300000); // 300 ms
            } catch (\Exception $e) {
                Log::error('AutoTraderSpot: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder(array $buy_order, $candleData, $targetProfit, $stopLossReductionPrecentage, $market): void
    {
        Log::info('AutoTraderSpot: Open order found for ' . $buy_order['symbol']);

        $current_price = BinanceApiService::getCurrentPrice($buy_order['symbol'], $market);
        $current_profit = (($current_price - $buy_order['price']) / $buy_order['price']) * 100;

        Log::info('AutoTraderSpot: Current Price: ' . $current_price);
        Log::info('AutoTraderSpot: Buy Order Price: ' . $buy_order['price']);
        Log::info('AutoTraderSpot: Current Profit: ' . $current_profit . '%');

        $newStopLoss = $buy_order['stopLoss'];
        $newStopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];
        if ($current_profit > $targetProfit && $current_price > $buy_order['previousPrice']) {

            // $newStopLossReductionPrecentage = min(0.7, $buy_order['stopLossReductionPrecentage'] * 0.9); // Tighter stop-loss as profit increases
            $newStopLoss = $current_price * (100 - $newStopLossReductionPrecentage) / 100;

            // Ensure stop-loss does not go below the buy price
            $newStopLoss = max($buy_order['stopLoss'], $newStopLoss, $buy_order['price'] * (1 + $targetProfit / 100) * (1 - $stopLossReductionPrecentage / 100));


            Log::info('AutoTraderSpot: Updated Stop-Loss Percentage: ' . $newStopLossReductionPrecentage . '%');
            Log::info('AutoTraderSpot: Updated Stop Loss: ' . $newStopLoss);
        }

        DB::table('orders')->where('orderId', $buy_order['orderId'])->update([
            'stopLossReductionPrecentage' => $newStopLossReductionPrecentage,
            'stopLoss' => $newStopLoss,
            'previousPrice' => $current_price,
            'rsiMin' => $buy_order['rsiMin'] > $candleData[count($candleData) - 1]['rsi6'] ? $candleData[count($candleData) - 1]['rsi6'] : $buy_order['rsiMin'],
            'obvMin' => $buy_order['obvMin'] > $candleData[count($candleData) - 1]['obv'] ? $candleData[count($candleData) - 1]['obv'] : $buy_order['obvMin'],
            'priceMin' => $buy_order['priceMin'] > $candleData[count($candleData) - 1]['close'] ? $candleData[count($candleData) - 1]['close'] : $buy_order['priceMin'],
        ]);
        if ($current_price < $newStopLoss) {
            Log::info('AutoTraderSpot: Current price below stop-loss, executing sell.');
            BinanceApiService::placeSellOrder($buy_order['orderId']);
        }
    }

    private static function managePriceLock($tradeInstance): void
    {
        Log::info('AutoTraderSpot: Price Locked for ' . $tradeInstance->symbol);

        $current_price = BinanceApiService::getCurrentPrice($tradeInstance->symbol, $tradeInstance->market);
        if ($current_price > $tradeInstance->priceLock) {
            BinanceApiService::placeBuyOrder($tradeInstance->symbol, $tradeInstance->interval, $tradeInstance->buyPrice, $tradeInstance->tradeAccount, $tradeInstance->market);
            // Reset price Lock for buying condition
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'priceLock' => 0,
            ]);
        } else {
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'priceLock' => min($current_price * 1.0009, $tradeInstance->priceLock),
            ]);
        }
    }
}
