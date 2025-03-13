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
                    $data = $candleData;

                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];

                    // Use Last completed Candle for checking conditions
                    $index--;

                    $macdLightGreenDistance = 0;
                    $loopIndex = $index;

                    while (true) {
                        if ($data[$loopIndex]['histogram'] <= $data[$loopIndex - 1]['histogram']) {
                            $macdLightGreenDistance++;
                        } else {
                            break;
                        }

                        $loopIndex--;
                    }



                    $isWorkerDispatched = DB::table('trade_handler')->where('id', $tradeInstance->id)->first()->isWorkerDispatched;



                    if (
                        $data[$index]['per'] < 0 && $data[$index - 1]['per'] < 0 && $data[$index - 2]['per'] > 0 &&

                        ($data[$index]['histogram'] > 0 ||  $data[$index - 1]['histogram'] > 0) &&

                        $data[$index]['dif'] < $data[$index - 1]['dif'] && $macdLightGreenDistance >= 6 &&

                        !$isWorkerDispatched

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
