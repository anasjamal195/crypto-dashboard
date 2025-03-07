<?php
/*

=======EXPERIMENT I========

Simple formula based on Resistance break with a threshold of 0.3 % 
For Long Trades in future market
Will Target Long Trades with a profit limit of 0.4% and a stop loss of support value


*/

namespace App\Services\SupportResistanceFakeBreakoutLiveTrades;

use App\CommonHelpers;
use App\Jobs\ThreadsFakeBreakout\LongThread;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\IdealTradeService;
use App\Services\MailerService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportResistanceFakeBreakoutLiveTradesLong
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function performLiveTrades($market, $account = null)
    {
        $openSymbols = DB::table('live_trades_future_results')->where('trade_acc', $account)->where('trade_status', 'open')->pluck('symbol');
        $tradeHandler = DB::table('trade_handler')->where('tradeAccount', $account)->where('market', $market)->where('position', 'LONG')->whereNotIn('symbol', $openSymbols)->where('isActive', 1)->get();
        Log::info('SupportResistanceFakeBreakout: Worker Started');

        foreach ($tradeHandler as $tradeInstance)
            try {
                $symbol = $tradeInstance->symbol;
                $trade_acc = $tradeInstance->tradeAccount;


                $open_order = CommonHelpers::checkOpenOrder($symbol, $tradeInstance->position, $market, $trade_acc);

                if (isset($open_order['is_open']) && $open_order['is_open']) {
                    continue;
                } else {

                    $supportResistance = MarketTrendService::getCurrentSupportResistanceValue($symbol, '5m', $market, [7]);
                    $candleData = $supportResistance['candleData'];



                    $currentCandle = $candleData[count($candleData) - 1];
                    $secondLastCandle = $candleData[count($candleData) - 2];
                    $thirdLastCandle = $candleData[count($candleData) - 3];

                    // For Short Trader

                    $newSupport = $supportResistance[7]['support'] * (1 + 0.5 / 100);


                    $index = count($candleData) - 1;
                    $turnOverPoint = $candleData[$index]['low'];

                    while (true) {
                        if ($turnOverPoint >  $candleData[$index]['low']) {
                            $turnoverPoint = $candleData[$index]['low'];
                        }

                        if ($candleData[$index]['low'] > $candleData[count($candleData) - 1]['low']) {
                            break;
                        }
                        $index--;
                    }

                    // Check if same trend persist after turnover point
                    $sameTrendTurnoverCondition = true;

                    for ($i = $index; $i <= (count($candleData) - 1); $i++) {
                        if ($candleData[$i]['close'] < $candleData[$i]['open']) {
                            $sameTrendTurnoverCondition = false;
                            break;
                        }
                    }




                    if (
                        $currentCandle['close'] > $currentCandle['open'] &&
                        $secondLastCandle['close'] > $secondLastCandle['open'] &&
                        $thirdLastCandle['close'] < $newSupport && $thirdLastCandle['open'] > $newSupport &&
                        $currentCandle['close'] >= $supportResistance[7]['support'] &&
                        $sameTrendTurnoverCondition &&
                        ($currentCandle['J'] > $currentCandle['K'] || $currentCandle['J'] > $currentCandle['D'])
                    ) {
                        Log::info('SupportResistanceFakeBreakout: Dispatching Long Thread... Coin:  ' . $symbol);

                        LongThread::dispatch($tradeInstance, $supportResistance, $turnOverPoint);
                        Cache::put($symbol . '_availability', 0, now()->addMinute());
                    }
                }
                CommonHelpers::delayMS(100);
            } catch (\Exception $e) {
                Log::error('SupportResistanceFakeBreakout: Error - ' . $e->getMessage());
                Log::error($e->getTraceAsString());
            }
        return true;
    }
}
