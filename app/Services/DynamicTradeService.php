<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicTradeService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}

    public static function checkDynamicTrades()
    {
        Log::info('Checking dynamic trades for all users.');

        foreach (User::all() as $user) {
            Log::info("Processing trades for user: {$user->id}");

            $dynamicTrades = DB::table('dynamic_trade_handler')
                ->where('market', 'SPOT')
                ->where('tradeAccount', $user->id)
                ->where('isActive', 1)
                ->get();

            foreach ($dynamicTrades as $trade) {
                $currentPrice = BinanceApiService::getCurrentPrice($trade->symbol, $trade->market);
                Log::info("Current price for {$trade->symbol} in market {$trade->market}: {$currentPrice}");

                if ($trade->side == 'BUY') {
                    if ($currentPrice < $trade->priceLock) {
                        Log::info("Updating price lock for BUY trade {$trade->id}: new price lock is {$currentPrice}");
                        DB::table('dynamic_trade_handler')
                            ->where('id', $trade->id)
                            ->update(['priceLock' => $currentPrice]);
                    } else if ($currentPrice > $trade->priceLock * (1 + ($trade->priceLockBuffer / 100))) {
                        Log::info("Executing BUY order for trade {$trade->id} due to price increase beyond buffer.");
                        BinanceApiService::placeDynamicBuyOrderSpot($trade->symbol, $trade->amount, $trade->tradeAccount);
                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'status' => 'FILLED',
                            'isActive' => 0,
                        ]);
                    }
                } else if ($trade->side == 'SELL') {
                    if ($currentPrice > $trade->priceLock) {
                        Log::info("Updating price lock for SELL trade {$trade->id}: new price lock is {$currentPrice}");
                        DB::table('dynamic_trade_handler')
                            ->where('id', $trade->id)
                            ->update(['priceLock' => $currentPrice]);
                    } else if ($currentPrice < $trade->priceLock * (1 - ($trade->priceLockBuffer / 100))) {
                        Log::info("Executing SELL order for trade {$trade->id} due to price decrease beyond buffer.");
                        BinanceApiService::placeDynamicSellOrderSpot($trade->symbol, $trade->qty, $trade->tradeAccount);
                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'status' => 'FILLED',
                            'isActive' => 0,
                        ]);
                    }
                }

                usleep(100000); // Add a 100ms delay for safety
            }
        }
    }
}
