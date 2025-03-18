<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For SHORT Trades in future market
Will Target SHORT Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\MacdFormula;

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

class MacdFormulaShort
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'SHORT')->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('ShortWorkerMACD: Worker Started');

        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;
                $openWorkersCount = DB::table('trade_handler')->where('isWorkerDispatched', true)->where('position', 'SHORT')->count();
                if ($openWorkersCount >= 9) {
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
                    $data = $candleData;

                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];

                    // Use Last completed Candle for checking conditions
                    $index--;

                    $macdLightGreenDistance = 0;
                    $loopIndex = $index;

                    while (true) {
                        if ($loopIndex == 1)
                            break;
                        if ($data[$loopIndex]['histogram'] <= $data[$loopIndex - 1]['histogram']) {
                            $macdLightGreenDistance++;
                        } else {
                            break;
                        }

                        $loopIndex--;
                    }



                    $isWorkerDispatched = DB::table('trade_handler')->where('id', $tradeInstance->id)->first()->isWorkerDispatched;

                    // New Formula
                    $macdDarkGreenDistance = 0;
                    $loopIndex = $index;

                    while (true) {
                        if ($loopIndex == 1)
                            break;
                        if ($data[$loopIndex]['histogram'] <= $data[$loopIndex - 1]['histogram']) {
                            $macdDarkGreenDistance++;
                        } else {
                            break;
                        }

                        $loopIndex--;
                    }


                    $totalGreenCandles = 0;
                    $loopIndex = $index;

                    while (true) {
                        if ($loopIndex == 1)
                            break;
                        if ($data[$loopIndex]['histogram'] < 0)
                            break;
                        $totalGreenCandles++;

                        $loopIndex--;
                    }

                    $volumeCrossover = false;
                    $loopIndex = $index;

                    while (true) {
                        if ($loopIndex == 1)
                            break;

                        if ($data[$loopIndex]['volumeMA5'] < $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] > $data[$loopIndex - 1]['volumeMA10']) {
                            $volumeCrossover = true;
                            break;
                        }
                        if ($data[$loopIndex]['volumeMA5'] > $data[$loopIndex]['volumeMA10'] && $data[$loopIndex - 1]['volumeMA5'] < $data[$loopIndex - 1]['volumeMA10']) {
                            break;
                        }
                        $loopIndex--;
                    }


                    $kdjCrossover = false;
                    $kdjthreshold = 0;
                    $loopIndex = $index;

                    while (true) {
                        if ($loopIndex == 1)
                            break;
                        if (
                            $data[$loopIndex]['J'] < $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100) &&
                            $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100)
                            &&
                            $data[$loopIndex]['J'] < $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100) &&
                            $data[$loopIndex - 1]['J'] >= $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100)
                        ) {
                            $kdjCrossover = true;
                            break;
                        }

                        if (
                            ($data[$loopIndex]['J'] > $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100) &&
                                $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['K'] * (1 - $kdjthreshold / 100)
                                &&
                                $data[$loopIndex]['J'] > $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100) &&
                                $data[$loopIndex - 1]['J'] <= $data[$loopIndex]['D'] * (1 - $kdjthreshold / 100))
                            ||
                            $loopIndex == 1
                        ) {
                            break;
                        }

                        $loopIndex--;
                    }

                    // Check KDJ approaching Crossover
                    $kdjApproachingCrossover = abs($data[$index]['K'] - $data[$index]['J']) < abs($data[$index - 1]['K'] - $data[$index - 1]['J']) &&
                        abs($data[$index]['D'] - $data[$index]['J']) < abs($data[$index - 1]['D'] - $data[$index - 1]['J']);



                    // Check downward wick
                    $upperWick = ($data[$index]['high'] - $data[$index]['open']);
                    $lowerWick = ($data[$index]['close'] - $data[$index]['low']);
                    $isDownwardWick = $data[$index]['close'] < $data[$index]['open'] && $lowerWick < $upperWick * 2;


                    $lastHighest = $data[$index]['high'];
                    $loopIndex = $index;
                    while (true) {
                        if ($loopIndex == 1)
                            break;
                        if ($data[$loopIndex]['high'] > $lastHighest) {
                            $lastHighest = $data[$loopIndex]['high'];
                        } else if ($data[$loopIndex]['high'] < $data[$index]['high'] || $loopIndex == 1) {
                            break;
                        }
                        $loopIndex--;
                    }


                    if (
                        // $data[$index]['per'] < 0 && $data[$index - 1]['per'] < 0 && $data[$index - 2]['per'] > 0 &&

                        // ($data[$index]['histogram'] > 0 ||  $data[$index - 1]['histogram'] > 0) &&

                        // $data[$index]['dif'] < $data[$index - 1]['dif'] && $macdLightGreenDistance >= 6 &&

                        $data[$index]['histogram'] > 0
                        && $isDownwardWick
                        && ($kdjCrossover || $kdjApproachingCrossover)
                        && $totalGreenCandles > 4
                        && $data[$index]['per'] <= -0.2
                        && $data[$index]['per'] > -0.6
                        && $data[$index]['close'] < $lastHighest * (1 - 0.7 / 100)
                        && $data[$index]['avl'] < $data[$index - 1]['avl']
                        && $data[$index]['dif'] < $data[$index - 1]['dif']
                        && $data[$index]['rsi6'] < $data[$index - 1]['rsi6'] - 10

                        && !$isWorkerDispatched

                    ) {
                        Log::info('ShortWorkerMACD: Dispatching Short Thread... Coin: ' . $symbol);
                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'isWorkerDispatched' => true,
                        ]);
                        ThreadsMACDShortThread::dispatch($tradeInstance, $supportResistance);
                        break;
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('ShortWorkerMACD: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
}
