<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For SHORT Trades in future market
Will Target SHORT Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\FutureLiveTrades;

use App\CommonHelpers;
use App\Jobs\Threads\ShortThread;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveTradeSHORTFutureServiceEXP1
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'SHORT')->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('FutureTraderShortEXP1: Worker Started');

        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;


                // Log::info('FutureTraderShortEXP1: Current Trade');
                // Log::info('FutureTraderShortEXP1: Coin: ' . $symbol);
                // Log::info('FutureTraderShortEXP1: Account: ' . $trade_acc);
                // Log::info('FutureTraderShortEXP1: Invested: ' . $buy_coin_price . ' $');

                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    continue;
                } else {

                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                    $candleData = $supportResistance['candleData'];



                    $CurrentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $supportResistanceContition = $CurrentCandle['close']  <  $supportResistance[7]['support'] &&
                        $secondLastCandle['close']  >  $supportResistance[7]['support'];



                    // Will skip this iteration is below value is false
                    $proceedCondition = $CurrentCandle['close'] < $CurrentCandle['open'] // Candle Should be in Bearish
                        && $CurrentCandle['close'] >= $supportResistance[7]['support'] * (1 - 0.0035); // Current Price should be above -0.3% of support





                    $maCondition = $CurrentCandle['ma7'] < $CurrentCandle['ma25'] && $CurrentCandle['ma25'] < $CurrentCandle['ma99'];

                    $maCandleDistance = 0;

                    // Find CROSSOVER in last N candles candles (MA7 from Below MA25)
                    // for ($i = count($candleData) - 2; $i >= 1; $i--) {
                    //     $maCondition =  ($candleData[$i + 1]['ma7'] < $candleData[$i + 1]['ma25']  && $candleData[$i - 1]['ma7'] > $candleData[$i - 1]['ma25']);
                    //     if ($maCondition) {
                    //         $maCandleDistance = (count($candleData) - 1) - $i;
                    //         break;
                    //     }
                    // }

                    // $maCondition =  $maCondition && $maCandleDistance <= 10;
                    // if ($maCondition) {
                    //     for ($i = count($candleData) - 2; $i >= (count($candleData) - 2) - $maCandleDistance; $i--) {
                    //         if ($candleData[$i]['close'] > $candleData[$i]['open'] && $candleData[$i - 1]['close'] > $candleData[$i - 1]['open']) {
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
                        if ($candleData[$indexCounter]['close'] < $candleData[$indexCounter]['open']) {
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


                    // Log::info('FutureTraderShortEXP1: Support: ' . $supportResistanceContition);
                    // Log::info('FutureTraderShortEXP1: MA Condition: ' . $maCondition);
                    // Log::info('FutureTraderShortEXP1: MA MACandleDistance: ' . $maCandleDistance);

                    if ($supportResistanceContition && $maCondition && $proceedCondition && $volumeCondition && Cache::get($symbol . '_availability', 1)) {
                        Log::info('FutureTraderShortEXP1: Dispatching Short Thread... Coin: ' . $symbol);

                        ShortThread::dispatch($tradeInstance, $supportResistance);
                        Cache::put($symbol . '_availability', 0, now()->addMinute());
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('FutureTraderShortEXP1: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
}
