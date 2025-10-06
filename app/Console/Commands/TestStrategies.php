<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\MarketStudyService;
use App\Services\BinanceApiService;
use App\Services\LiveTrader\BNBUSDT;
use App\Services\LiveTrader\BTCUSDT;
use App\Services\LiveTrader\ETHUSDT;
use App\Services\LiveTrader\HBARUSDT;
use App\Services\LiveTrader\SOLUSDT;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestStrategies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-strategies';

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



        $symbol = 'BTCUSDT';
        $interval =  '15m';



        $calender = CommonHelpers::generateCalendar(2024);


        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        DB::table('opened_trades')->delete();

        $tradeCount = 0;
        $pnl = 0;
        $longCount = 0;
        $shortCount = 0;

        $aggressivePnl = $trendlinePnl = $doublePnl = $fvgPnl = 0;

        $aggressiveCount = $trendlineCount = $doubleCount = $fvgCount = 0;

        $fee = 0;
        foreach ($calender['months'] as $month) {

            $startingTime = $month['startTime']; // 2024 1st Janurary 00:00:00
            $endingTime = $month['endTime']; // 2025 1st October 23:45:00
            $limit15m = intval(($endingTime - $startingTime) / (CommonHelpers::$binanceIntervals['15m'] * 60 * 1000));
            $limit1h = intval(($endingTime - $startingTime) / (CommonHelpers::$binanceIntervals['1h'] * 60 * 1000));
            $limit4h = intval(($endingTime - $startingTime) / (CommonHelpers::$binanceIntervals['4h'] * 60 * 1000));

            $timestamp = $startingTime;

            $data = BinanceApiService::getCandleStickDataExtendedInternal($symbol, $interval, $limit15m, $timestamp, 'FUTURE');
            $data1hRaw = BinanceApiService::getCandleStickDataExtendedInternal($symbol, '1h', $limit1h, $timestamp, 'FUTURE');
            $data4hRaw = BinanceApiService::getCandleStickDataExtendedInternal($symbol, '4h', $limit4h, $timestamp, 'FUTURE');


            $openTrade = null;

            $minAllowedRatio = 0;
            $tradeSetupDetails = null;

            $this->info('Starting....');
            // TRENDLINE

            $tradeSetupDetails = null;
            $openTrade = null;

            foreach ($data as $index => $candle) {

                if ($index < 32 || $index > (count($data) - 1)) {
                    continue;
                }


                $percentage = round((($index + 1) / count($data)) * 100, 1);


                system('clear');

                $this->info("===========================================");
                $this->info("              MONTHLY REPORT              ");
                $this->info("===========================================");

                $this->info("Current Month : " . $month['label']);
                $this->info("Progress      : " . $percentage . " %");
                $this->info("Candle        : " . $candle['timestampReadable']);
                $this->info("Symbol        : " . $symbol);

                $this->info("-------------------------------------------");
                $this->info("              DETAILED STATS               ");
                $this->info("-------------------------------------------");


                $this->info(str_pad("Aggressive Count", 20) . ": " . $aggressiveCount);
                $this->info(str_pad("Trendline Count", 20) . ": " . $trendlineCount);
                $this->info(str_pad("FVG Count", 20) . ": " . $fvgCount);
                $this->info(str_pad("Double Breakout Count", 20) . ": " . $doubleCount);
                $this->info('');
                $this->info(str_pad("Total Count", 20) . ": " . $tradeCount);

                $this->info("-------------------------------------------");
                $this->info("                PNL STATS                  ");
                $this->info("-------------------------------------------");

                $this->info(str_pad("Aggressive PnL", 20) . ": " . $aggressivePnl . " %");
                $this->info(str_pad("Trendline PnL", 20) . ": " . $trendlinePnl . " %");
                $this->info(str_pad("FVG PnL", 20) . ": " . $fvgPnl . " %");
                $this->info(str_pad("Double Breakout PnL", 20) . ": " . $doublePnl . " %");

                $this->info("");
                $this->info(str_pad("Gross PnL", 20) . ": " . $pnl . " %");
                $this->info(str_pad("Fee", 20) . ": " . $fee . " %");
                $this->info(str_pad("Net PnL", 20) . ": " . ($pnl - $fee) . " %");

                $this->info("===========================================");






                if ($symbol === 'BTCUSDT') {
                    BTCUSDT::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);
                }



                if ($openTrade) {
                    $closingPrice = null;

                    if ($openTrade['direction'] === 'LONG') {
                        if ($data[$index]['high'] >=  $openTrade['tp']) {
                            $closingPrice = $openTrade['tp'];
                        }
                        if ($data[$index]['low'] <  $openTrade['sl']) {
                            $closingPrice = $openTrade['sl'];
                        }
                    } else if ($openTrade['direction'] === 'SHORT') {
                        if ($data[$index]['low'] <=  $openTrade['tp']) {
                            $closingPrice = $openTrade['tp'];
                        }
                        if ($data[$index]['high'] >  $openTrade['sl']) {
                            $closingPrice = $openTrade['sl'];
                        }
                    }


                    // Trade Closed final, resetting params and plotting
                    if ($closingPrice) {

                        $tradeCount++;
                        $fee += 0.15;
                        $profit = null;

                        if ($openTrade['direction'] === 'LONG') {
                            $longCount++;
                            $profit = CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                        } else {
                            $shortCount++;

                            $profit = -1 *  CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                        }



                        $pnl += $profit;

                        switch ($openTrade['strategy_name']) {
                            case 'AGGRESSIVE':
                                $aggressivePnl += $profit;
                                $aggressiveCount++;
                                break;
                            case 'DOUBLE_BREAKOUTS':
                                $doublePnl += $profit;
                                $doubleCount++;
                                break;
                            case 'FVG':
                                $fvgPnl += $profit;
                                $fvgCount++;
                                break;
                            case 'TRENDLINE':
                                $trendlinePnl += $profit;
                                $trendlineCount++;
                                break;
                        }
                        $openTrade['closingPrice'] = $closingPrice;
                        $openTrade['symbol'] = $symbol;
                        $openTrade['interval'] = $interval;
                        $openTrade['closingTimestamp'] = $data[$index]['binance_timestamp'];
                        $openTrade['profit'] = $profit;
                        $openTrade['timestamp'] = $timestamp;
                        $openTrade['zones'] = $openTrade['zones'] ? json_encode($openTrade['zones']) : null;
                        $openTrade['fvg'] = $openTrade['profit'] ? json_encode($openTrade['fvg']) : null;
                        $openTrade['trendline'] = $openTrade['trendline'] ? json_encode($openTrade['trendline']) : null;
                        $openTrade['calender'] =  json_encode($calender);
                        DB::table('opened_trades')->insert($openTrade);
                        $openTrade = null;
                    } else {
                        continue;
                    }
                }


                if (!$tradeSetupDetails) {
                    // TESTMODE


                    // if ($aggressive_waiting_candles == 0) {

                    $testModeOptions = [
                        'data' => $data,
                        'index' => $index,
                        'data1hRaw' => $data1hRaw,
                        'data4hRaw' => $data4hRaw,
                        'zoneProcessing' => false,
                        'enabledStrategies' => [
                            'AGGRESSIVE',
                            'DOUBLE_BREAKOUTS', // Finalized with maximum accuracy (70% - 100%) 
                            'TRENDLINE', // Finalized with maximum accuracy (70% - 100%)
                            'FVG', // Finalized with maximum accuracy (60% - 100%) - Low Frequency
                        ]
                    ];

                    if ($symbol === 'BTCUSDT') {
                        $tradeSetupDetails = BTCUSDT::runTrader($testModeOptions);
                    } else if ($symbol === 'ETHUSDT') {
                        $tradeSetupDetails = ETHUSDT::runTrader($testModeOptions);
                    } else if ($symbol === 'SOLUSDT') {
                        $tradeSetupDetails = SOLUSDT::runTrader($testModeOptions);
                    } else if ($symbol === 'BNBUSDT') {
                        $tradeSetupDetails = BNBUSDT::runTrader($testModeOptions);
                    } else if ($symbol === 'HBARUSDT') {
                        $tradeSetupDetails = HBARUSDT::runTrader($testModeOptions);
                    }
                    // } else {
                    //     $aggressive_waiting_candles--;
                    // }
                }

                // LOGIC WHEN ZONE IS ACTIVE
                if ($tradeSetupDetails) {

                    if ($tradeSetupDetails['opening_rule'] === 'waiting_till_next_touch' && $tradeSetupDetails['signal_timestamp'] !== $data[$index]['binance_timestamp']) {
                        if (
                            $data[$index]['low'] <= $tradeSetupDetails['trigger_price']
                            && $tradeSetupDetails['direction'] === 'LONG'

                        ) {

                            $tp = $tradeSetupDetails['tp'];
                            $sl = $tradeSetupDetails['sl'];
                            $entryPrice = $tradeSetupDetails['trigger_price'];

                            $openTrade = [
                                'openingPrice' => $entryPrice,
                                'tp' => $tp,
                                'sl' => $sl,
                                'direction' => 'LONG',
                                'openingTimestamp' => $data[$index]['binance_timestamp'],
                                'strategy_name' => $tradeSetupDetails['strategy_name'],
                                'zones' => [
                                    'top_zone' => $tradeSetupDetails['top_zone'],
                                    'middle_zone' => $tradeSetupDetails['middle_zone'],
                                    'bottom_zone' => $tradeSetupDetails['bottom_zone'],
                                ],
                                'fvg' => isset($tradeSetupDetails['fvg']) ? $tradeSetupDetails['fvg'] : null,
                                'trendline' => isset($tradeSetupDetails['trendline']) ? $tradeSetupDetails['trendline'] : null,

                            ];
                        }
                        if (
                            $data[$index]['high'] >= $tradeSetupDetails['trigger_price']
                            && $tradeSetupDetails['direction'] === 'SHORT'
                        ) {

                            $tp = $tradeSetupDetails['tp'];
                            $sl = $tradeSetupDetails['sl'];
                            $entryPrice = $tradeSetupDetails['trigger_price'];

                            $openTrade = [
                                'openingPrice' => $entryPrice,
                                'tp' => $tp,
                                'sl' => $sl,
                                'direction' => 'SHORT',
                                'strategy_name' => $tradeSetupDetails['strategy_name'],

                                'openingTimestamp' => $data[$index]['binance_timestamp'],
                                'zones' => [
                                    'top_zone' => $tradeSetupDetails['top_zone'],
                                    'middle_zone' => $tradeSetupDetails['middle_zone'],
                                    'bottom_zone' => $tradeSetupDetails['bottom_zone'],
                                ],
                                'fvg' => isset($tradeSetupDetails['fvg']) ? $tradeSetupDetails['fvg'] : null,
                                'trendline' => isset($tradeSetupDetails['trendline']) ? $tradeSetupDetails['trendline'] : null,

                            ];
                        }
                    } else if ($tradeSetupDetails['opening_rule'] === 'waiting_till_next_touch_confirm_close' && $tradeSetupDetails['signal_timestamp'] !== $data[$index]['binance_timestamp']) {
                        if (
                            $data[$index]['low'] <= $tradeSetupDetails['trigger_price']
                            && $data[$index]['close'] > $tradeSetupDetails['trigger_price']
                            && $tradeSetupDetails['direction'] === 'LONG'

                        ) {

                            $tp = $tradeSetupDetails['tp'];
                            // $sl = $data[$index]['low'] * (1 - 0.15 / 100);
                            // $sl = $tradeSetupDetails['sl'];

                            $entryPrice = $data[$index]['close'];

                            $recentLow = CommonHelpers::getRecentPivot($data, $index, 'low', 3, 'wick', $entryPrice * (1 - 0.09 / 100));

                            if ($recentLow) {
                                $sl = $recentLow['value'];
                            } else {
                                continue;
                            }

                            $currentZone = null;
                            if (
                                $sl <= $tradeSetupDetails['top_zone']['top'] && $sl >= $tradeSetupDetails['top_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['top_zone'];
                            }
                            if (
                                $sl <= $tradeSetupDetails['middle_zone']['top'] && $sl >= $tradeSetupDetails['middle_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['middle_zone'];
                            }
                            if (
                                $sl <= $tradeSetupDetails['bottom_zone']['top'] && $sl >= $tradeSetupDetails['bottom_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['bottom_zone'];
                            }



                            if (
                                $currentZone
                                && CommonHelpers::mapValueToRange($sl, $currentZone['bottom'], $currentZone['top']) <= 60
                            ) {
                                $sl = $currentZone['bottom'];
                            }





                            $openTrade = [
                                'openingPrice' => $entryPrice,
                                'tp' => $tp,
                                'sl' => $sl,
                                'direction' => 'LONG',
                                'openingTimestamp' => $data[$index]['binance_timestamp'],
                                'strategy_name' => $tradeSetupDetails['strategy_name'],
                                'zones' => [
                                    'top_zone' => $tradeSetupDetails['top_zone'],
                                    'middle_zone' => $tradeSetupDetails['middle_zone'],
                                    'bottom_zone' => $tradeSetupDetails['bottom_zone'],
                                ],
                                'fvg' => isset($tradeSetupDetails['fvg']) ? $tradeSetupDetails['fvg'] : null,
                                'trendline' => isset($tradeSetupDetails['trendline']) ? $tradeSetupDetails['trendline'] : null,
                                'orderblock' => isset($tradeSetupDetails['orderblock']) ? $tradeSetupDetails['orderblock'] : null,

                            ];
                        }
                        if (
                            $data[$index]['high'] >= $tradeSetupDetails['trigger_price']
                            && $data[$index]['close'] < $tradeSetupDetails['trigger_price']
                            && $tradeSetupDetails['direction'] === 'SHORT'
                        ) {

                            $tp = $tradeSetupDetails['tp'];
                            // $sl = $data[$index]['high'] * (1 + 0.15 / 100);
                            // $sl = $tradeSetupDetails['sl'];
                            $entryPrice = $data[$index]['close'];


                            $recentHigh = CommonHelpers::getRecentPivot($data, $index, 'high', 3, 'wick', $entryPrice * (1 + 0.09 / 100));

                            if ($recentHigh) {
                                $sl = $recentHigh['value'];
                            } else {
                                continue;
                            }

                            // CHeck for zone intersection on sl

                            $currentZone = null;
                            if (
                                $sl <= $tradeSetupDetails['top_zone']['top'] && $sl >= $tradeSetupDetails['top_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['top_zone'];
                            }
                            if (
                                $sl <= $tradeSetupDetails['middle_zone']['top'] && $sl >= $tradeSetupDetails['middle_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['middle_zone'];
                            }
                            if (
                                $sl <= $tradeSetupDetails['bottom_zone']['top'] && $sl >= $tradeSetupDetails['bottom_zone']['bottom']
                            ) {
                                $currentZone = $tradeSetupDetails['bottom_zone'];
                            }



                            if (
                                $currentZone
                                && CommonHelpers::mapValueToRange($sl, $currentZone['bottom'], $currentZone['top']) >= 40
                            ) {
                                $sl = $currentZone['top'];
                            }
                            $openTrade = [
                                'openingPrice' => $entryPrice,
                                'tp' => $tp,
                                'sl' => $sl,
                                'direction' => 'SHORT',
                                'strategy_name' => $tradeSetupDetails['strategy_name'],

                                'openingTimestamp' => $data[$index]['binance_timestamp'],
                                'zones' => [
                                    'top_zone' => $tradeSetupDetails['top_zone'],
                                    'middle_zone' => $tradeSetupDetails['middle_zone'],
                                    'bottom_zone' => $tradeSetupDetails['bottom_zone'],
                                ],
                                'fvg' => isset($tradeSetupDetails['fvg']) ? $tradeSetupDetails['fvg'] : null,
                                'trendline' => isset($tradeSetupDetails['trendline']) ? $tradeSetupDetails['trendline'] : null,
                                'orderblock' => isset($tradeSetupDetails['orderblock']) ? $tradeSetupDetails['orderblock'] : null,

                            ];
                        }
                    } else if ($tradeSetupDetails['opening_rule'] === 'immidiate_opening') {

                        $tp = $tradeSetupDetails['tp'];
                        $sl = $tradeSetupDetails['sl'];
                        $entryPrice = $tradeSetupDetails['trigger_price'];

                        $openTrade = [
                            'openingPrice' => $entryPrice,
                            'tp' => $tp,
                            'sl' => $sl,
                            'direction' => $tradeSetupDetails['direction'],
                            'strategy_name' => $tradeSetupDetails['strategy_name'],

                            'openingTimestamp' => $data[$index]['binance_timestamp'],
                            'zones' => [
                                'top_zone' => $tradeSetupDetails['top_zone'],
                                'middle_zone' => $tradeSetupDetails['middle_zone'],
                                'bottom_zone' => $tradeSetupDetails['bottom_zone'],
                            ],
                            'fvg' => isset($tradeSetupDetails['fvg']) ? $tradeSetupDetails['fvg'] : null,
                            'trendline' => isset($tradeSetupDetails['trendline']) ? $tradeSetupDetails['trendline'] : null,
                        ];
                    }


                    // CHECK For Ratio confirmation
                    if ($openTrade) {

                        $tradeSetupDetails = null;
                        $ratio = abs($openTrade['openingPrice'] - $openTrade['tp']) / abs($openTrade['openingPrice'] - $openTrade['sl']);

                        $skippingConditions = (
                            $ratio < $minAllowedRatio
                        );


                        if ($skippingConditions) {
                            $openTrade = null;
                            $tradeSetupDetails = null;
                            if (
                                $ratio < $minAllowedRatio
                            )
                                $openingMarkers[] = CommonHelpers::generateLabelPlot($data[$index]['binance_timestamp'], 'purple', 'Ratio');

                            continue;
                        }
                    }
                }
                // Recover trade loop if Opening doesnt met
                if ($tradeSetupDetails) {
                    $currentZone = DB::table('sd_zones')->where('status', 'active')->first();

                    if ($currentZone) {
                        if (isset($tradeSetupDetails['current_zone_id']) && $tradeSetupDetails['current_zone_id'] !== $currentZone->id) {
                            $tradeSetupDetails = null;
                        }
                    }
                }
            }

            exit;
        }
        $this->info('Completed');



        dd("Completed");
    }
}
