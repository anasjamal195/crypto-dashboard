<?php

namespace App\Console\Commands\LiveTrader;

use App\CommonHelpers;
use App\Jobs\LiveTrader\ExecuteTrade;
use App\Services\BinanceApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleOpenings extends Command
{
    protected $signature = 'app:handle-openings';
    protected $description = 'Continuously checks for new trade openings based on opening type';

    public function handle()
    {
        $this->info("🚀 HandleOpenings worker started...");

        while (true) {
            $tradeSetups = DB::table('trade_setup_details')->where('status', 'WAITING')->get();

            if ($tradeSetups->isEmpty()) {
                Log::debug("No WAITING trade setups found. Sleeping...");
                sleep(2);
                continue;
            }

            Log::info("Found {$tradeSetups->count()} waiting trade setups.");

            foreach ($tradeSetups as $setup) {
                $openTrade = null;

                $context = [
                    'setup_id'   => $setup->id,
                    'symbol'     => $setup->symbol,
                    'direction'  => $setup->direction,
                    'interval'   => $setup->interval,
                    'tp'         => $setup->tp,
                    'sl'         => $setup->sl,
                    'rule'       => $setup->opening_rule,
                    'trigger'    => $setup->trigger_price,
                ];

                try {
                    $current_system_time = (int) round(microtime(true) * 1000);

                    if ($setup->opening_rule === 'immidiate_opening') {
                        Log::info("Immediate opening triggered.", $context);

                        $openTrade = [
                            'symbol' => $setup->symbol,
                            'openingPrice'     => $setup->trigger_price,
                            'tp'               => $setup->tp,
                            'sl'               => $setup->sl,
                            'direction'        => $setup->direction,
                            'interval'         => $setup->interval,
                            'openingTimestamp' => $current_system_time,
                            'zones'            => json_decode($setup->zones ?? '[]', true),
                            'fvg'              => json_decode($setup->fvg ?? '[]', true),
                            'current_zone'     => json_decode($setup->current_zone ?? '[]', true),
                            'account_id'       => $setup->account_id,
                            'setup_id'         => $setup->id,
                            'strategy_name'     => $setup->strategy_name

                        ];
                    } elseif ($setup->opening_rule === 'waiting_till_next_touch') {
                        $currentPrice = BinanceApiService::getCurrentPrice($setup->symbol, 'FUTURE');
                        $isTouching = (
                            ($currentPrice <= $setup->trigger_price && $setup->direction === 'LONG') ||
                            ($currentPrice >= $setup->trigger_price && $setup->direction === 'SHORT')
                        );

                        Log::debug("Touch check for {$setup->symbol}: CurrentPrice={$currentPrice}, Trigger={$setup->trigger_price}, Result=" . ($isTouching ? 'TOUCH' : 'NO TOUCH'), $context);

                        if ($isTouching) {
                            Log::info("Opening triggered by price touch.", array_merge($context, ['price' => $currentPrice]));

                            $openTrade = [
                                'symbol' => $setup->symbol,
                                'openingPrice'     => $currentPrice,
                                'tp'               => $setup->tp,
                                'sl'               => $setup->sl,
                                'direction'        => $setup->direction,
                                'interval'         => $setup->interval,
                                'openingTimestamp' => $current_system_time,
                                'zones'            => json_decode($setup->zones ?? '[]', true),
                                'fvg'              => json_decode($setup->fvg ?? '[]', true),
                                'current_zone'     => json_decode($setup->current_zone ?? '[]', true),
                                'account_id'       => $setup->account_id,
                                'setup_id'         => $setup->id,
                                'strategy_name'     => $setup->strategy_name
                            ];
                        }

                        // Recovery check
                        $currentZoneLatest = DB::table('sd_zones')->where('symbol', $setup->symbol)->where('interval', $setup->interval)->where('status', 'active')->first();
                        $currentZone = json_decode($setup->current_zone ?? '[]', true);

                        if ($currentZoneLatest && $currentZone && $currentZone['id'] !== $currentZoneLatest->id) {
                            DB::table('trade_setup_details')
                                ->where('id', $setup->id)
                                ->update([
                                    'status'         => 'FAILED',
                                    'faliure_reason' => json_encode([
                                        'zone_skipping' => 'Current active zone was skipped and price entered a new zone'
                                    ]),
                                ]);

                            Log::warning("Trade setup failed due to zone skipping.", $context);
                            continue;
                        }
                    }


                    // Dispatch trade execution
                    if ($openTrade) {
                        Log::info("✅ Dispatching trade execution job.", $context);
                        DB::table('trade_setup_details')
                            ->where('id', $setup->id)
                            ->update([
                                'status'         => 'PROCESSING'
                            ]);
                        ExecuteTrade::dispatch($openTrade);
                    }
                } catch (\Throwable $e) {
                    Log::error("❌ Error processing trade setup.", array_merge($context, [
                        'exception' => $e->getMessage(),
                        'trace'     => $e->getTraceAsString(),
                    ]));
                }
                sleep(1);
            }
        }
    }
}
