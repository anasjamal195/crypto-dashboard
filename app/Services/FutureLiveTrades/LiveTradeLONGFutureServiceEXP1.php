<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For Long Trades in future market
Will Target Long Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\FutureLiveTrades;

use App\CommonHelpers;
use App\Jobs\Threads\LongThread;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeLONGFutureServiceEXP1
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'LONG')->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('FutureTraderLongEXP1: Worker Started');

        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;

                // Log::info('FutureTraderLongEXP1: Current Trade');
                // Log::info('FutureTraderLongEXP1: Coin: ' . $symbol);
                // Log::info('FutureTraderLongEXP1: Account: ' . $trade_acc);
                // Log::info('FutureTraderLongEXP1: Invested: ' . $buy_coin_price . ' $');
                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);

                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    continue;
                } else {

                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                    $candleData = $supportResistance['candleData'];

                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];

                    $resistance = $supportResistance[7]['resistance'] * (1 - 1 / 100);

                    $supportResistanceContition = $CurrentCandle['close']  >  $resistance &&
                        $secondLastCandle['close']  <  $resistance;


                    // Will skip this iteration is below value is false
                    $proceedCondition = $CurrentCandle['close'] > $CurrentCandle['open'] // Candle Should be in Bullish
                        && $CurrentCandle['close'] <= $resistance * (1 + 1/100); // Current Price should be Below +0.3% of resistance


                    $maCondition = $CurrentCandle['ma7'] > $CurrentCandle['ma25'] && $CurrentCandle['ma25'] > $CurrentCandle['ma99'];
                    $maCandleDistance = 0;

                    // // Find CROSSOVER in last N candles candles (MA7 from Below MA25)
                    // for ($i = count($candleData) - 2; $i >= 1; $i--) {
                    //     $maCondition =  ($candleData[$i + 1]['ma7'] > $candleData[$i + 1]['ma25']  && $candleData[$i - 1]['ma7'] < $candleData[$i - 1]['ma25']);
                    //     if ($maCondition) {
                    //         $maCandleDistance = (count($candleData) - 1) - $i;
                    //         break;
                    //     }
                    // }

                    // $maCondition =  $maCondition && $maCandleDistance <= 7;

                    // if ($maCondition) {
                    //     for ($i = count($candleData) - 2; $i >= (count($candleData) - 2) - $maCandleDistance; $i--) {
                    //         if ($candleData[$i]['close'] < $candleData[$i]['open'] && $candleData[$i - 1]['close'] < $candleData[$i - 1]['open']) {
                    //             $maCondition = false;
                    //             break;
                    //         }
                    //     }
                    // }

                    $averageTrailingVolume = 0;
                    $volumeCandlesCount = 0;
                    $indexCounter = count($candleData) - 1;
                    $loopVolume = true;
                    while ($loopVolume) {
                        if ($candleData[$indexCounter]['close'] > $candleData[$indexCounter]['open']) {
                            $averageTrailingVolume += $candleData[$indexCounter]['volume'];
                            $volumeCandlesCount++;
                            $indexCounter--;
                        } else {
                            $loopVolume = false;
                        }
                    }

                    $averageTrailingVolume = $averageTrailingVolume && $volumeCandlesCount ? $averageTrailingVolume / $volumeCandlesCount :  0;
                    $volumeMultiplier = 1.3;
                    $volumeCondition = $CurrentCandle['volume'] > $averageTrailingVolume * $volumeMultiplier && $averageTrailingVolume != 0;

                    // Log::info('FutureTraderLongEXP1: Resistance: ' . $supportResistanceContition);
                    // Log::info('FutureTraderLongEXP1: MA Condition: ' . $maCondition);
                    // Log::info('FutureTraderLongEXP1: MA MACandleDistance: ' . $maCandleDistance);





                    if ($supportResistanceContition && $maCondition && $proceedCondition && $volumeCondition && Cache::get($symbol . '_availability', 1)) {
                        Log::info('FutureTraderShortEXP1: Dispatching Long Thread... Coin:  ' . $symbol);

                        LongThread::dispatch($tradeInstance, $supportResistance);
                        Cache::put($symbol . '_availability', 0, now()->addMinute());
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('FutureTraderLongEXP1: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
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
            // $data = BinanceApiService::getCandleStickData($coin, $interval, 1000, null, $market);
            // $idealBuying = IdealTradeService::getIdealBuyingCandles($data);
            // $averages = IdealTradeService::getAverages($idealBuying);

            // Dumping Trade Handler data

            if (CommonHelpers::getMetaValue($user_id, 'is_auto_update_enable' . $meta_prefix, true) !== 'on') {
                continue;
            }


            // Handle LONG Trades

            // Remove Coins that are not in priority queue
            $leftoverEntries = DB::table('trade_handler')->whereNotIn('symbol', $coins)->where('tradeAccount', $user_id)->where('position', 'LONG')->where('market', $market)->where('interval', $interval)->get();
            foreach ($leftoverEntries as $leftoverCoin) {
                $open_order = CommonHelpers::checkOpenOrder($leftoverCoin->symbol, 'LONG', $market, $user_id);
                if (!$open_order['is_open']) {
                    DB::table('trade_handler')->where('id', $leftoverCoin->id)->delete();
                }
            }

            // Insert new priority data
            $trade_handler = [
                'market' => $market,
                'symbol' => $coin,
                'interval' => $interval,
                'position' => 'LONG',
                'leverage' => 1,
                'buyPrice' => CommonHelpers::getMetaValue($user_id, 'buy_price' . $meta_prefix, 6),
                'tradeAccount' => $user_id,
                'targetProfit' => CommonHelpers::getMetaValue($user_id, 'target_profit' . $meta_prefix, 0.4),
                'rsiThreshold' => 0,
                'obvLimit' => 0,
                'stochLimit' => 0,
                'isActive' => 1
            ];
            $existing = DB::table('trade_handler')->where('tradeAccount', $user_id)->where('market', $market)->where('position', 'LONG')->where('interval', $interval)->where('symbol', $coin)->first();
            if ($existing) {
                DB::table('trade_handler')
                    ->where('id', $existing->id)
                    ->update($trade_handler);
            } else {
                // Insert new record
                DB::table('trade_handler')->insert($trade_handler);
            }

            // Handle SHORT Trades
            // Remove Coins that are not in priority queue
            $leftoverEntries = DB::table('trade_handler')->whereNotIn('symbol', $coins)->where('tradeAccount', $user_id)->where('position', 'SHORT')->where('market', $market)->where('interval', $interval)->get();
            foreach ($leftoverEntries as $leftoverCoin) {
                $open_order = CommonHelpers::checkOpenOrder($leftoverCoin->symbol, 'SHORT', $market, $user_id);
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
                'leverage' => 1,
                'buyPrice' => CommonHelpers::getMetaValue($user_id, 'buy_price' . $meta_prefix, 6),
                'tradeAccount' => $user_id,
                'targetProfit' => CommonHelpers::getMetaValue($user_id, 'target_profit' . $meta_prefix, 0.4),
                'rsiThreshold' => 0,
                'obvLimit' => 0,
                'stochLimit' => 0,
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
            CommonHelpers::delayMS(600);
        }
        // dd($coins);

        return $coins;
    }
}
