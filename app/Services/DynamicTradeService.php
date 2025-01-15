<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DynamicTradeService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}
    public static function checkDynamicTrades()
    {
        foreach (User::all() as $user) {

            $dynamicTrades = DB::table('dynamic_trade_handler')->where('market','SPOT')->where('tradeAccount', $user->id)->get();
            foreach ($dynamicTrades as $trade) {
                $currentPrice = BinanceApiService::getCurrentPrice($trade->symbol, $trade->market);
                if ($trade->side == 'BUY') {
                    if ($currentPrice < $trade->priceLock) {
                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'priceLock' => $currentPrice,
                        ]);
                    } else if ($currentPrice > $trade->priceLock * (1 + ($trade->priceLockBuffer / 100))) {

                        BinanceApiService::placeDynamicBuyOrderSpot($trade->symbol, $trade->amount, $trade->tradeAccount);

                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'status' => 'FILLED',
                            'active' => 0,
                        ]);
                    }
                } else if ($trade->side == 'SELL') {

                    if ($currentPrice > $trade->priceLock) {
                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'priceLock' => $currentPrice,
                        ]);
                    } else if ($currentPrice < $trade->priceLock * (1 - ($trade->priceLockBuffer / 100))) {

                        BinanceApiService::placeDynamicSellOrderSpot($trade->symbol, $trade->quantity, $trade->tradeAccount);

                        DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                            'status' => 'FILLED',
                            'active' => 0,
                        ]);
                    }
                }
                usleep(100000); // Add a 200ms delay for safety
            }
        }
    }
}
