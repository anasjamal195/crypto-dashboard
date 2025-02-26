<?php

namespace App\Console\Commands\Supervisors\DynamicTradesWorker;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\DynamicTradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FutureDynamicTradeWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:future-dynamic-trade-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // Test command to fill in profit losses of previous trades
        $openTrades = DB::table('live_trades_future_results')->where('type', 'open')->orderBy('created_at', 'DESC')->get();
        foreach ($openTrades as $trade) {

            $openOrder = $trade;
            $closeOrder = DB::table('live_trades_future_results')->where('orderId', $trade->pairId)->first();
            if (!$closeOrder)
                continue;
            // For close order

            $feeDetails = BinanceApiService::getFeeDetails($openOrder->orderId);

            $feeUsdt = 0;
            $realizedPnl = 0;
            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            // For close order
            $feeDetails = BinanceApiService::getFeeDetails($closeOrder->orderId);

            foreach ($feeDetails as $fee) {
                $feeUsdt += floatval($fee['commission']);
                $realizedPnl += floatval($fee['realizedPnl']);
            }

            // Update this data in db
            DB::table('live_trades_future_results')->where('orderId', $openOrder->orderId)->update([
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,

            ]);
            DB::table('live_trades_future_results')->where('orderId', $closeOrder->orderId)->update([
                'feeUsdt' => $feeUsdt,
                'realizedPnl' => $realizedPnl,
            ]);
            CommonHelpers::delayMS(100);
        }
        dd("Dump Complete");










        // while (true) {
        //     try {
        //         DynamicTradeService::checkDynamicTradesFUTURE();
        //         usleep(10000); // 10ms delay
        //     } catch (\Exception $th) {
        //         Log::error('An error occured: ' . $th);
        //     }
        // }
    }
}
