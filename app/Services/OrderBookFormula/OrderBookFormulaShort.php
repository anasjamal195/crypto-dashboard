<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For SHORT Trades in future market
Will Target SHORT Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\OrderBookFormula;

use App\CommonHelpers;
use App\Jobs\Threads\ShortThread;
use App\Jobs\ThreadsMACD\ShortThread as ThreadsMACDShortThread;
use App\Jobs\ThreadsOrderBook\ShortThread as ThreadsOrderBookShortThread;
use App\Models\OrderBookSnapshot;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderBookFormulaShort
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'SHORT')->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('ShortWorkerOrderBook: Worker Started');

        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;
                $openWorkersCount = DB::table('trade_handler')->where('isWorkerDispatched', true)->count();
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


                   
                    $triggerPrice = 0;

                    $timestamp = $data[$index]['timestampReadable'];
                    $snapshot = OrderBookSnapshot::where('snapshot_time', '>=', $timestamp)
                        ->where('snapshot_time', '<=', Carbon::parse($timestamp)->addMinutes(5))
                        ->where('symbol', $symbol)
                        ->where('depth', 1000)
                        ->where('signal', 'LONG')
                        ->where('long_strength', '>=', 8)
                        ->latest('snapshot_time')
                        ->first();

                    if (!$snapshot) {
                        continue;
                    }

                    $entry_points = array_map(function ($level) {
                        return $level['price'];
                    }, $snapshot->resistance_levels);

                    $triggerPrice = min($entry_points);
                    $triggerIndex = $index;

                    if (
                       
                        !(
                            $data[$index]['dif'] > $data[$index]['dea']
                            && $data[$index - 1]['dif'] < $data[$index - 1]['dea']
                        )
                    ) {
                        Log::info('ShortWorkerOrderBook: Dispatching Short Thread... Coin: ' . $symbol);
                        DB::table('trade_handler')->where('id', $tradeInstance->id)->update([
                            'isWorkerDispatched' => true,
                        ]);
                        ThreadsOrderBookShortThread::dispatch($tradeInstance, $supportResistance, $triggerPrice, $triggerIndex);
                        break;
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('ShortWorkerOrderBook: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
                // dd($e);
                // sendEmailException($e, 'API Store Txn Alert: Exception Alert!');
            }
        return true;
    }
}
