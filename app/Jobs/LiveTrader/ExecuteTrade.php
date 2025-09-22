<?php

namespace App\Jobs\LiveTrader;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\HyperLiquidApiService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteTrade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 0; // run indefinitely
    protected $trade;

    // STATIC CONFIGS
    public static $isSpot = false;
    public static $activeExchange = 'binance';
    public static $buyPrice = 25; // USD
    public static $leverage = 5;

    public function __construct($trade)
    {
        $this->trade = $trade;
    }

    public function handle(): void
    {
        $symbol   = $this->trade['symbol'] ?? null;
        $setup_id = $this->trade['setup_id'] ?? null;

        Log::info("[ExecuteTrade] Starting job", [
            'setup_id' => $setup_id,
            'symbol'   => $symbol,
            'trade'    => $this->trade,
        ]);

        $failureReasons = [];

        try {
            $tradeType     = $this->trade['direction'];
            $tradeAccount  = $this->trade['account_id'];
            $strategy_name = $this->trade['strategy_name'];
            $position      = $this->trade['direction'];
            $sl            = $this->trade['sl'];
            $tp            = $this->trade['tp'];
            $slPercent     = CommonHelpers::getPercentDiff($this->trade['openingPrice'], $sl);
            $tpPercent     = CommonHelpers::getPercentDiff($this->trade['openingPrice'], $tp);





            // ========== CHECK EXISTING OPEN ORDER ==========
            $open_order = (self::$isSpot && $tradeType === 'LONG')
                ? CommonHelpers::checkOpenOrder($symbol, $position, 'SPOT', $tradeAccount)
                : CommonHelpers::checkOpenOrder($symbol, $position, 'FUTURE', $tradeAccount);

            if ($open_order['is_open']) {
                $failureReasons['opened_order'] = 'Already opened order detected for this symbol and account.';
                Log::warning("[ExecuteTrade] Skipping trade - open order exists", [
                    'setup_id' => $setup_id,
                    'symbol'   => $symbol,
                    'open_order' => $open_order,
                ]);
            }


            if (!CommonHelpers::getMetaValue($tradeAccount, 'enable_' . strtolower($position) . '_multithread', 0)) {
                $failureReasons['opening_disabled'] = 'Already opened order detected for this symbol and account.';
                Log::warning("[ExecuteTrade] Skipping trade - Opening Disabled for " . $position, [
                    'setup_id' => $setup_id,
                    'symbol'   => $symbol,
                    'open_order' => $this->trade,
                ]);
            }

            // ========== PLACE ORDER ==========
            $response = null;
            if (empty($failureReasons)) {

                try {
                    if (self::$isSpot && $tradeType === 'LONG') {
                        $response = self::$activeExchange === 'binance'
                            ? BinanceApiService::placeBuyOrderSpot($symbol, self::$buyPrice, 'BUY', self::$leverage, $tradeAccount, $strategy_name, null, null, false, $slPercent, $tpPercent)
                            : HyperLiquidApiService::placeBuyOrderSpot($symbol, self::$buyPrice, 'BUY', self::$leverage, $tradeAccount, $strategy_name, null, null, false, $slPercent, $tpPercent);
                    } else {
                        $response = self::$activeExchange === 'binance'
                            ? BinanceApiService::openMarketPositionLiveTrader($symbol, self::$buyPrice, $position === 'LONG' ? 'BUY' : 'SELL', self::$leverage, $tradeAccount, $strategy_name, null, null, false, $slPercent, $tpPercent)
                            : HyperLiquidApiService::openMarketPositionLiveTrader($symbol, self::$buyPrice, $position === 'LONG' ? 'BUY' : 'SELL', self::$leverage, $tradeAccount, $strategy_name, null, null, false, $slPercent, $tpPercent);
                    }

                    Log::info("[ExecuteTrade] Trade order response", [
                        'setup_id' => $setup_id,
                        'symbol'   => $symbol,
                        'response' => $response,
                    ]);
                } catch (\Throwable $e) {
                    $failureReasons['order_error'] = $e->getMessage();
                    Log::error("[ExecuteTrade] Failed to place order", [
                        'setup_id'  => $setup_id,
                        'symbol'    => $symbol,
                        'exception' => $e->getMessage(),
                        'trace'     => $e->getTraceAsString(),
                    ]);
                }


                // ========== CONFIRM OPENING ==========
                if (isset($response['orderId'])) {
                    DB::table('trade_setup_details')
                        ->where('id', $setup_id)
                        ->update([
                            'status'        => 'OPENED',
                            'trigger_price' => $response['price'] ?? null,
                            'open_order_id' => $response['orderId'],
                        ]);

                    Log::info("[ExecuteTrade] Trade successfully opened", [
                        'setup_id' => $setup_id,
                        'symbol'   => $symbol,
                        'order_id' => $response['orderId'],
                    ]);

                    // ========== MONITOR TRADE ==========
                    $iteration = 0;
                    while (true) {
                        try {

                            // Refreshing TP and SL
                            $setup = DB::table('trade_setup_details')->where('id', $setup_id)->first();
                            if ($setup) {
                                $sl = $setup->sl;
                                $tp = $setup->tp;
                            }


                            $open_order = self::$isSpot && $tradeType === 'LONG'
                                ? CommonHelpers::checkOpenOrder($symbol, $position, 'SPOT', $tradeAccount)
                                : CommonHelpers::checkOpenOrder($symbol, $position, 'FUTURE', $tradeAccount);

                            if (!(isset($open_order['is_open']) && $open_order['is_open'])) {
                                Log::info("[ExecuteTrade] Trade closed externally", [
                                    'setup_id' => $setup_id,
                                    'symbol'   => $symbol,
                                ]);
                                break;
                            }

                            $open_order = $open_order['order'];
                            $tableName = $open_order['market'] === 'FUTURE' ? 'live_trades_future_results' : 'live_trades_spot_results';

                            $currentPrice = BinanceApiService::getCurrentPrice($symbol, self::$isSpot ? 'SPOT' : 'FUTURE');
                            $closingType  = null;

                            $currentProfit = ($position === 'SHORT' ? -1 : 1)  * CommonHelpers::getPercentDiff($open_order['price'], $currentPrice, true);
                            $targetProfit = ($position === 'SHORT' ? -1 : 1)  * CommonHelpers::getPercentDiff($open_order['price'], $tp, true);
                            DB::table($tableName)->where('orderId', $open_order['orderId'])->update([
                                'previousPrice' => $open_order['currentPrice'],
                                'currentPrice' => $currentPrice,
                                'currentProfit' => $currentProfit,
                                'targetProfit' => $targetProfit,
                                'updated_at' => Carbon::now()->toDateTimeString(),

                            ]);
                            if (($position === 'LONG' && $currentPrice >= $tp) ||
                                ($position === 'SHORT' && $currentPrice <= $tp)
                            ) {
                                $closingType = 'profit';
                            } elseif (($position === 'LONG' && $currentPrice <= $sl) ||
                                ($position === 'SHORT' && $currentPrice >= $sl)
                            ) {
                                $closingType = 'loss';
                            }

                            if ($closingType) {
                                Log::info("[ExecuteTrade] Closing trade triggered", [
                                    'setup_id'    => $setup_id,
                                    'symbol'      => $symbol,
                                    'closingType' => $closingType,
                                    'price'       => $currentPrice,
                                ]);

                                $closingResponse = $open_order['market'] === 'SPOT'
                                    ? (self::$activeExchange === 'binance'
                                        ? BinanceApiService::placeSellOrderSpot($open_order['orderId'])
                                        : HyperLiquidApiService::placeSellOrderSpot($open_order['orderId']))
                                    : (self::$activeExchange === 'binance'
                                        ? BinanceApiService::closeMarketPositionLiveTrader($open_order['orderId'])
                                        : HyperLiquidApiService::closeMarketPositionLiveTrader($open_order['orderId']));

                                if (isset($closingResponse['orderId'])) {
                                    DB::table('trade_setup_details')
                                        ->where('id', $setup_id)
                                        ->update([
                                            'status'         => 'CLOSED',
                                            'close_order_id' => $closingResponse['orderId'],
                                        ]);

                                    Log::info("[ExecuteTrade] Trade closed successfully", [
                                        'setup_id' => $setup_id,
                                        'symbol'   => $symbol,
                                        'order_id' => $closingResponse['orderId'],
                                    ]);
                                } else {
                                    $failureReasons['invalid_closing_response'] = 'Closing response invalid';
                                    Log::error("[ExecuteTrade] Invalid closing response", [
                                        'setup_id' => $setup_id,
                                        'symbol'   => $symbol,
                                        'response' => $closingResponse,
                                    ]);
                                }

                                break;
                            }
                        } catch (\Exception $e) {
                            $failureReasons['monitoring_error'] = $e->getMessage();
                            Log::error("[ExecuteTrade] Error during monitoring", [
                                'setup_id'  => $setup_id,
                                'symbol'    => $symbol,
                                'exception' => $e->getMessage(),
                                'trace'     => $e->getTraceAsString(),
                            ]);
                            break;
                        }

                        // Avoid flooding logs, log every 50 iterations
                        if ($iteration % 50 === 0) {
                            Log::info("[ExecuteTrade] Monitoring trade", [
                                'setup_id' => $setup_id,
                                'symbol'   => $symbol,
                                'iteration' => $iteration,
                            ]);
                        }

                        $iteration++;
                        CommonHelpers::delayS(1);
                    }
                } else {
                    $failureReasons['invalid_opening_response'] = 'Opening response invalid';
                    Log::error("[ExecuteTrade] Invalid opening response", [
                        'setup_id' => $setup_id,
                        'symbol'   => $symbol,
                        'response' => $response,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $failureReasons['fatal_error'] = $e->getMessage();
            Log::critical("[ExecuteTrade] Fatal job error", [
                'setup_id'  => $setup_id,
                'symbol'    => $symbol,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }

        // ========== MARK FAILED ==========
        if (!empty($failureReasons)) {
            DB::table('trade_setup_details')
                ->where('id', $setup_id)
                ->update([
                    'status'         => 'FAILED',
                    'faliure_reason' => json_encode($failureReasons),
                ]);

            Log::warning("[ExecuteTrade] Trade marked as FAILED", [
                'setup_id' => $setup_id,
                'symbol'   => $symbol,
                'reasons'  => $failureReasons,
            ]);
        }
    }
}
