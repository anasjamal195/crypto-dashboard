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

    public static function checkDynamicTradesSPOT()
    {
        Log::info('Checking dynamic trades for all users.');

        foreach (User::all() as $user) {
            Log::info("Processing trades for user: {$user->id}");

            $dynamicTrades = DB::table('dynamic_trades_spot')
                ->where('tradeAccount', $user->id)
                ->where('isActive', 1)
                ->get();

            foreach ($dynamicTrades as $trade) {
                $currentPrice = BinanceApiService::getCurrentPrice($trade->symbol, 'SPOT');
                Log::info("Current price for {$trade->symbol} in market SPOT: {$currentPrice}");

                if ($trade->side == 'BUY') {
                    if ($currentPrice < $trade->priceLockBuy) {
                        Log::info("Updating price lock for BUY trade {$trade->id}: new price lock is {$currentPrice}");
                        DB::table('dynamic_trades_spot')
                            ->where('id', $trade->id)
                            ->update(['priceLockBuy' => $currentPrice]);
                    } else if ($currentPrice > $trade->priceLockBuy * (1 + ($trade->priceLockBuyBuffer / 100))) {
                        Log::info("Executing BUY order for trade {$trade->id} due to price increase beyond buffer.");
                        BinanceApiService::placeDynamicBuyOrderSpot($trade->symbol, $trade->amount, $trade->tradeAccount, $trade);
                        DB::table('dynamic_trades_spot')->where('id', $trade->id)->update([
                            'status' => 'Filled',
                            'isActive' => 0,
                        ]);
                    }
                } else if ($trade->side == 'SELL') {
                    if ($currentPrice > $trade->priceLockSell) {
                        Log::info("Updating price lock for SELL trade {$trade->id}: new price lock is {$currentPrice}");
                        DB::table('dynamic_trades_spot')
                            ->where('id', $trade->id)
                            ->update(['priceLockSell' => $currentPrice]);
                    } else if ($currentPrice < $trade->priceLockSell * (1 - ($trade->priceLockSellBuffer / 100))) {
                        Log::info("Executing SELL order for trade {$trade->id} due to price decrease beyond buffer.");
                        BinanceApiService::placeDynamicSellOrderSpot($trade->symbol, $trade->qty, $trade->tradeAccount, $trade);
                        DB::table('dynamic_trades_spot')->where('id', $trade->id)->update([
                            'status' => 'FILLED',
                            'isActive' => 0,
                        ]);
                    }
                } else if ($trade->side == 'TRADEPAIR') {
                    if ($trade->status == 'PENDING-BUY') {

                        if ($currentPrice < $trade->priceLockBuy) {
                            Log::info("Updating price lock for TRADEPAIR Buy trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trades_spot')
                                ->where('id', $trade->id)
                                ->update(['priceLockBuy' => $currentPrice]);
                        } else if ($currentPrice > $trade->priceLockBuy * (1 + ($trade->priceLockBuyBuffer / 100))) {
                            Log::info("Executing BUY order for trade {$trade->id} due to price increase beyond buffer.");
                            BinanceApiService::placeDynamicBuyOrderSpot($trade->symbol, $trade->amount, $trade->tradeAccount, $trade);
                            DB::table('dynamic_trades_spot')->where('id', $trade->id)->update([
                                'status' => 'PENDING-SELL',
                            ]);
                        }
                    } else if ($trade->status == 'PENDING-SELL') {

                        if ($trade->stopLoss != 0 && $currentPrice < $trade->stopLoss) {
                            Log::info("Executing SELL order for trade {$trade->id} due to price decrease beyond Stop Loss.");
                            $qty = DB::table('dynamic_trades_spot_results')->where('tradeId', $trade->id)->where('side', 'BUY')->first()->qty;
                            BinanceApiService::placeDynamicSellOrderSpot($trade->symbol, $qty, $trade->tradeAccount, $trade);
                            DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                            continue;
                        }

                        if ($currentPrice > $trade->priceLockSell) {
                            Log::info("Updating price lock for TRADEPAIR Sell trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trade_handler')
                                ->where('id', $trade->id)
                                ->update(['priceLockSell' => $currentPrice]);
                        } else if ($currentPrice < $trade->priceLockSell * (1 - ($trade->priceLockSellBuffer / 100))) {
                            Log::info("Executing SELL order for trade {$trade->id} due to price decrease beyond buffer.");
                            $qty = DB::table('dynamic_trades_spot_results')->where('tradeId', $trade->id)->where('side', 'BUY')->first()->qty;
                            BinanceApiService::placeDynamicSellOrderSpot($trade->symbol, $qty, $trade->tradeAccount, $trade);
                            DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                        }
                    }
                }

                usleep(100000); // Add a 100ms delay for safety
            }
        }
    }

    public static function checkDynamicTradesFUTURE()
    {
        Log::info('Checking dynamic trades for all users.');

        foreach (User::all() as $user) {
            Log::info("Processing trades for user: {$user->id}");

            $dynamicTrades = DB::table('dynamic_trades_future')
                ->where('tradeAccount', $user->id)
                ->where('isActive', 1)
                ->get();

            foreach ($dynamicTrades as $trade) {
                $currentPrice = BinanceApiService::getCurrentPrice($trade->symbol, 'FUTURE');
                Log::info("Current price for {$trade->symbol} in market FUTURE: {$currentPrice}");

                if ($trade->position == 'BUY') {
                    if ($trade->status == 'PENDING-OPEN') {
                        if ($currentPrice < $trade->priceLockOpen) {
                            Log::info("Updating price lock for OPEN BUY trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trades_future')
                                ->where('id', $trade->id)
                                ->update(['priceLockOpen' => $currentPrice]);
                        } else if ($currentPrice > $trade->priceLockOpen * (1 + ($trade->priceLockOpenBuffer / 100))) {
                            Log::info("Executing OPEN BUY order for trade {$trade->id} due to price increase beyond buffer.");
                            BinanceApiService::openMarketPosition($trade->symbol, $trade->amount, $trade->position, $trade->leverage ?? 0, $trade->tradeAccount, $trade);
                            if ($trade->allowClose)
                                DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                    'status' => 'PENDING-CLOSE',
                                ]);
                            else
                                DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                    'status' => 'FILLED',
                                    'isActive' => 0,
                                ]);
                        }
                    } else if ($trade->status == 'PENDING-CLOSE') {

                        if ($trade->stopLoss != 0 && $currentPrice < $trade->stopLoss) {
                            $openOrderId = DB::table('dynamic_trades_future_results')->where('tradeId', $trade->id)->first()->orderId;
                            Log::info("Executing OPEN BUY order for trade {$trade->id} due to price increase beyond Stop Loss. Order Id: " . $openOrderId);
                            BinanceApiService::closeMarketPosition($openOrderId, $trade);
                            DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                        }
                        if ($currentPrice > $trade->priceLockClose) {
                            Log::info("Updating price lock for CLOSE BUY trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trade_handler')
                                ->where('id', $trade->id)
                                ->update(['priceLockClose' => $currentPrice]);
                        } else if ($currentPrice < $trade->priceLockClose * (1 + ($trade->priceLockCloseBuffer / 100))) {
                            $openOrderId = DB::table('dynamic_trades_future_results')->where('tradeId', $trade->id)->first()->orderId;
                            Log::info("Executing OPEN BUY order for trade {$trade->id} due to price increase beyond buffer. Order Id: " . $openOrderId);
                            BinanceApiService::closeMarketPosition($openOrderId, $trade);
                            DB::table('dynamic_trade_handler')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                        }
                    }
                } else if ($trade->position == 'SELL') {
                    if ($trade->status == 'PENDING-OPEN') {
                        if ($currentPrice > $trade->priceLockOpen) {
                            Log::info("Updating price lock for OPEN SELL trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trades_future')
                                ->where('id', $trade->id)
                                ->update(['priceLockOpen' => $currentPrice]);
                        } else if ($currentPrice < $trade->priceLockOpen * (1 - ($trade->priceLockOpenBuffer / 100))) {
                            Log::info("Executing OPEN SELL order for trade {$trade->id} due to price increase beyond buffer.");
                            BinanceApiService::openMarketPosition($trade->symbol, $trade->amount, $trade->position, $trade->leverage ?? 0, $trade->tradeAccount, $trade);
                            if ($trade->allowClose)
                                DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                    'status' => 'PENDING-CLOSE',
                                ]);
                            else
                                DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                    'status' => 'FILLED',
                                    'isActive' => 0,
                                ]);
                        }
                    } else if ($trade->status == 'PENDING-CLOSE') {

                        if ($trade->stopLoss != 0 && $currentPrice > $trade->stopLoss) {
                            $openOrderId = DB::table('dynamic_trades_future_results')->where('tradeId', $trade->id)->first()->orderId;
                            Log::info("Executing SELL BUY order for trade {$trade->id} due to price increase beyond Stop Loss. Order Id: " . $openOrderId);
                            BinanceApiService::closeMarketPosition($openOrderId, $trade);
                            DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                        }
                        if ($currentPrice < $trade->priceLockClose) {
                            Log::info("Updating price lock for CLOSE SELL trade {$trade->id}: new price lock is {$currentPrice}");
                            DB::table('dynamic_trades_future')
                                ->where('id', $trade->id)
                                ->update(['priceLockClose' => $currentPrice]);
                        } else if ($currentPrice > $trade->priceLockClose * (1 + ($trade->priceLockCloseBuffer / 100))) {
                            $openOrderId = DB::table('dynamic_trades_future_results')->where('tradeId', $trade->id)->first()->orderId;
                            Log::info("Executing SELL BUY order for trade {$trade->id} due to price increase beyond buffer. Order Id: " . $openOrderId);

                            BinanceApiService::closeMarketPosition($openOrderId, $trade);
                            DB::table('dynamic_trades_future')->where('id', $trade->id)->update([
                                'status' => 'FILLED',
                                'isActive' => 0,
                            ]);
                        }
                    }
                }

                usleep(100000); // Add a 100ms delay for safety
            }
        }
    }
}
