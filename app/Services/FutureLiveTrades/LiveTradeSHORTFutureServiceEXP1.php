<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For SHORT Trades in future market
Will Target SHORT Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\FutureLiveTrades;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeSHORTFutureServiceEXP1
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market)
    {
        DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->update([
            'priceLock' => 0,
        ]);
        $tradeHandler = DB::table('trade_handler')->where('market', $market)->where('position', 'SHORT')->where('isActive', 1)->get();

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

                $priceLockBuffer = $tradeInstance->priceLockBuffer;
                $leverage = $tradeInstance->leverage;
                $candleData = BinanceApiService::getCandleStickData($symbol, '5m', 300, null, $market);


                Log::info('FutureTraderShortEXP1: Current Trade');
                Log::info('FutureTraderShortEXP1: Coin: ' . $symbol);
                Log::info('FutureTraderShortEXP1: Interval: ' . $interval);
                Log::info('FutureTraderShortEXP1: Account: ' . $trade_acc);
                Log::info('FutureTraderShortEXP1: Invested: ' . $buy_coin_price . ' $');
                Log::info('FutureTraderShortEXP1: RSI Threshold: ' . $rsiThreshold);
                Log::info('FutureTraderShortEXP1: OBV Limit: ' . $obvLimit);
                Log::info('FutureTraderShortEXP1: Price Lock Buffer: ' . $priceLockBuffer);

                $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [8]);
                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);


                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    self::manageOpenOrder($tradeInstance, $open_order['order'], $candleData, $targetProfit, $stopLossReductionPrecentage, $market, $supportResistance);
                } else {
                    // Max open order should be 5
                    if (CommonHelpers::getOpenOrderCount($interval, $market, $trade_acc) >= 1) {
                        continue;
                    }


                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];
                    $fourthLastCandle = $candleData[count($candleData) - 4];
                    $fifthLastCandle = $candleData[count($candleData) - 5];
                    $sixthLastCandle = $candleData[count($candleData) - 6];


                    // $supportResistanceContition =   $supportResistance[5]['support'] >= $supportResistance[10]['support'] &&
                    //     $supportResistance[10]['support'] >= $supportResistance[15]['support'] &&
                    //     $secondLastCandle['close'] <= $supportResistance[5]['support'] * (1 - 0.3 / 100) &&
                    //     $CurrentCandle['close'] <= $supportResistance[5]['support'] * (1 - 0.3 / 100) &&
                    //     $thirdLastCandle['close'] > $supportResistance[5]['support'] * (1 - 0.3 / 100);


                    $supportResistanceContition = $secondLastCandle['close']  <  $supportResistance[8]['support'];

                   // CROSSOVER within last two candles (MA7 from Above MA25)
                    $maCondition =  ($thirdLastCandle['ma7'] < $thirdLastCandle['ma25']  && $fifthLastCandle['ma7'] > $fifthLastCandle['ma25']) ||
                                    ($fourthLastCandle['ma7'] < $fourthLastCandle['ma25']  && $sixthLastCandle['ma7'] > $sixthLastCandle['ma25']);
                     


                    Log::info('FutureTraderShortEXP1: Support: ' . $supportResistanceContition);



                    if ($tradeInstance->priceLock != 0) {
                        self::managePriceLock($tradeInstance);
                    } else if ($supportResistanceContition && $maCondition) {

                        Log::info('FutureTraderShortEXP1: Support: ' . $supportResistance[5]['support']);
                        Log::info('FutureTraderShortEXP1: Support Limit: ' . $supportResistance[5]['support'] * (1 + 0.3 / 100));
                        Log::info('FutureTraderShortEXP1: Second Last Close: ' .   $secondLastCandle['close']);
                        Log::info('FutureTraderShortEXP1: Third Last Close: ' .   $thirdLastCandle['close']);
                        Log::info('FutureTraderShortEXP1: Current Close: ' .   $CurrentCandle['close']);

                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'priceLock' => BinanceApiService::getCurrentPrice($symbol, $market) * (1 - $priceLockBuffer / 100),
                        ]);
                    }
                }
                CommonHelpers::delayMS(1000);
            } catch (\Exception $e) {
                Log::error('FutureTraderShortEXP1: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
    private static function manageOpenOrder($tradeInstance, array $buy_order, $candleData, $targetProfit, $stopLossReductionPrecentage, $market, $supportResistance): void
    {
        Log::info('FutureTraderShortEXP1: Open order found for ' . $buy_order['symbol']);

        $current_price = BinanceApiService::getCurrentPrice($buy_order['symbol'], $market);
        $current_profit = (($current_price - $buy_order['price']) / $buy_order['price']) * 100 * -1;


        Log::info('FutureTraderShortEXP1: Current Price: ' . $current_price);
        Log::info('FutureTraderShortEXP1: Buy Order Price: ' . $buy_order['price']);
        Log::info('FutureTraderShortEXP1: Current Profit: ' . $current_profit . '%');
        Log::info('FutureTraderShortEXP1: StopLoss: ' . $buy_order['stopLoss'] . '%');


        $newStopLoss = $buy_order['stopLoss'];
        $newStopLossReductionPrecentage = $buy_order['stopLossReductionPrecentage'];
        if ($current_profit > $targetProfit && $current_price < $buy_order['previousPrice']) {

            // $newStopLossReductionPrecentage = min(0.7, $buy_order['stopLossReductionPrecentage'] * 0.9); // Tighter stop-loss as profit increases
            $newStopLoss = $current_price * (100 + $newStopLossReductionPrecentage) / 100;

            // Ensure stop-loss does not go below the buy price
            $newStopLoss = min($buy_order['stopLoss'], $newStopLoss, $buy_order['price'] * (1 - $targetProfit / 100) * (1 + $stopLossReductionPrecentage / 100));


            Log::info('FutureTraderShortEXP1: Updated Stop-Loss Percentage: ' . $newStopLossReductionPrecentage . '%');
            Log::info('FutureTraderShortEXP1: Updated Stop Loss: ' . $newStopLoss);
        }

        DB::table('live_trades_future_results')->where('orderId', $buy_order['orderId'])->update([
            'stopLossReductionPrecentage' => $newStopLossReductionPrecentage,
            'stopLoss' => $newStopLoss,
            'previousPrice' => $current_price,
            'currentPrice' => $current_price,
            'currentSupport' => $supportResistance[5]['support'],
            'currentResistance' => $supportResistance[5]['resistance'],
            'currentProfit' => $current_profit,
            'targetProfit' => $targetProfit,
        ]);

        // if ($current_price > $newStopLoss || $current_price > $supportResistance['support'] * (1 + 0.005)) {
        if ($current_price > $newStopLoss) {
            Log::info('FutureTraderShortEXP1: Current price below stop-loss, executing sell.');
            BinanceApiService::closeMarketPositionLiveTrader($buy_order['orderId']);
        }
    }

    private static function managePriceLock($tradeInstance): void
    {
        Log::info('FutureTraderShortEXP1: Price Locked for ' . $tradeInstance->symbol);

        $current_price = BinanceApiService::getCurrentPrice($tradeInstance->symbol, $tradeInstance->market);
        if ($current_price < $tradeInstance->priceLock) {

            $highestPrice = $current_price;
            $isLoop = true;
            while ($isLoop) {
                $latestPrice = BinanceApiService::getCurrentPrice($tradeInstance->symbol, $tradeInstance->market);
                if ($highestPrice < $latestPrice) {
                    $highestPrice = $latestPrice;
                } else if ($latestPrice < $highestPrice * 0.9991) {
                    BinanceApiService::openMarketPositionLiveTrader($tradeInstance->symbol, $tradeInstance->buyPrice, $tradeInstance->position === 'LONG' ? 'BUY' : 'SELL', $tradeInstance->leverage, $tradeInstance->tradeAccount,'MA7/MA25 Crossover with Support Resistance Break (SHORT)');
                    $isLoop = false;
                }
                CommonHelpers::delayMS(1000);
            }
            // Reset price Lock for buying condition
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'priceLock' => 0,
            ]);
        } else {
            DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                'priceLock' => max($current_price * 0.9991, $tradeInstance->priceLock),
            ]);
        }
    }

    public static function updateTradeHandler($interval, $market = 'SPOT', $user_id)
    {

        $meta_prefix = '';
        if ($market == 'SPOT')
            $meta_prefix = '_spot';
        else
            $meta_prefix = '_future';
        $limit = CommonHelpers::getMetaValue($user_id, 'live_trade_coin_count' . $meta_prefix, 10);
        $coins = array_map(function ($value) {
            return $value['symbol'];
        }, json_decode(json_encode(CommonHelpers::getPriorityQueue($interval, $market, $limit)), true));


        foreach ($coins as $coin) {
            $data = BinanceApiService::getCandleStickData($coin, $interval, 1000, null, $market);
            $idealBuying = IdealTradeService::getIdealBuyingCandles($data);
            $averages = IdealTradeService::getAverages($idealBuying);

            // Dumping Trade Handler data
            if (CommonHelpers::getMetaValue($user_id, 'is_auto_update_enable' . $meta_prefix, true) !== 'on') {
                continue;
            }

            // Remove Coins that are not in priority queue
            $leftoverEntries = DB::table('trade_handler')->whereNotIn('symbol', $coins)->where('tradeAccount', $user_id)->where('position', 'SHORT')->where('market', $market)->where('interval', $interval)->get();
            foreach ($leftoverEntries as $leftoverCoin) {
                $open_order = CommonHelpers::checkOpenOrder($leftoverCoin->symbol, $interval, $market, $user_id);
                if (!$open_order['is_open']) {
                    DB::table('trade_handler')->where('id', $leftoverCoin->id)->delete();
                }
            }

            // Insert new priority data
            $trade_handler = [
                'market' => $market,
                'symbol' => $coin,
                'interval' => $interval,
                'position' => 'SHORT',
                'leverage' => 2,
                'buyPrice' => CommonHelpers::getMetaValue($user_id, 'buy_price' . $meta_prefix, 6),
                'tradeAccount' => $user_id,
                'targetProfit' => CommonHelpers::getMetaValue($user_id, 'target_profit' . $meta_prefix, 0.4),
                'rsiThreshold' => $averages['rsi6'],
                'obvLimit' => $averages['previousObvHigh'] ? (($averages['previousObvHigh'] - $averages['obv']) / $averages['previousObvHigh']) * 100 : 100,
                'stochLimit' =>  $averages['stoch_rsi'] * 2,
                'isActive' => 1
            ];

            $existing = DB::table('trade_handler')->where('tradeAccount', $user_id)->where('position', 'SHORT')->where('market', $market)->where('interval', $interval)->where('symbol', $coin)->first();
            if ($existing) {
                DB::table('trade_handler')
                    ->where('id', $existing->id)
                    ->update($trade_handler);
            } else {
                // Insert new record
                DB::table('trade_handler')->insert($trade_handler);
            }

            CommonHelpers::delayMS(5000);
        }
        // dd($coins);

        return $coins;
    }
}
