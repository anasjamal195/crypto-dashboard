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
use App\Jobs\ThreadsMACD\ShortThread as ThreadsMACDShortThread;
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
                $openWorkersCount = DB::table('trade_handler')->where('isWorkerDispatched', true)->count();
                if ($openWorkersCount >= 17) {
                    continue;
                }


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);
                // dd($open_order);
                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    continue;
                } else {

                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                    $candleData = $supportResistance['candleData'];


                    $index = count($candleData) - 1;
                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];


                    $support = $supportResistance[7]['support'] * (1 + 1.2 / 100);
                    $supportResistanceContition = $currentCandle['close']  <  $support &&
                        $secondLastCandle['close']  >  $support;

                    // check if previous or current candle is below 1%
                    $secondLastCandlePer = (($secondLastCandle['close'] - $secondLastCandle['open']) / $secondLastCandle['open']) * 100;
                    $thirdLastCandlePer = (($thirdLastCandle['close'] - $thirdLastCandle['open']) / $thirdLastCandle['open']) * 100;

                    $candlePercentageCondition = $secondLastCandlePer <= 1 && $thirdLastCandlePer <= 1;

                    // Will skip this iteration is below value is false
                    $proceedCondition = $currentCandle['close'] < $currentCandle['open'] // Candle Should be in Bearish
                        && $currentCandle['close'] >= $support * (1 - 1.2 / 100); // Current Price should be above -0.3% of support





                    $maCondition = $currentCandle['ma7'] < $currentCandle['ma25'] && $currentCandle['ma25'] < $currentCandle['ma99'];
                    // $maCondition = true;

                    $maCandleDistance = 0;



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
                    $volumeCondition = $currentCandle['volume'] > $averageTrailingVolume * $volumeMultiplier && $averageTrailingVolume != 0;



                    $isWorkerDispatched = DB::table('trade_handler')->where('id', $tradeInstance->id)->first()->isWorkerDispatched;

                    $data = $candleData;
                    if ($supportResistanceContition && $candlePercentageCondition && $maCondition && $proceedCondition && $volumeCondition && !$isWorkerDispatched) {
                        Log::info('FutureTraderShortEXP1: Dispatching Short Thread... Coin: ' . $symbol);
                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'isWorkerDispatched' => true,
                        ]);
                        ShortThread::dispatch($tradeInstance, $supportResistance);
                        break;
                    } 
                    // else if (
                    //     $data[$index - 1]['histogram'] < 0 &&

                    //     $data[$index - 2]['histogram'] > 0 &&  $data[$index - 2]['histogram'] > $data[$index - 3]['histogram'] / 2 &&
                    //     $data[$index - 3]['histogram'] > 0 &&  $data[$index - 3]['histogram'] > $data[$index - 4]['histogram'] / 2 &&

                    //     $data[$index - 2]['histogram'] < $data[$index - 3]['histogram'] &&
                    //     ($currentCandle['J'] < $currentCandle['K'] || $currentCandle['J'] < $currentCandle['D']) &&

                    //     $data[$index - 1]['rsi6'] < $data[$index - 2]['rsi6'] &&

                    //     $data[$index]['close'] < $data[$index]['open'] && 

                    //     !$isWorkerDispatched

                    // ) {
                    //     Log::info('FutureTraderShortEXP1: Dispatching Short Thread... Coin: ' . $symbol);
                    //     DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                    //         'isWorkerDispatched' => true,
                    //     ]);
                    //     ThreadsMACDShortThread::dispatch($tradeInstance, $supportResistance);
                    //     break;
                    // }
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
