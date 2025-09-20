<?php

namespace App\Console\Commands;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use App\Services\LiveTrader\BTCUSDT;
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
        $timestamp = null;
        $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, 1000, $timestamp, 'FUTURE');
        $data1hRaw = BinanceApiService::getCandleStickDataPast($symbol, '1h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');
        $data4hRaw = BinanceApiService::getCandleStickDataPast($symbol, '4h', 1000, $data[count($data) - 1]['binance_timestamp'], 'FUTURE');

        $openingMarkers = [];
        $lines = [];
        $equations = [];
        $trades = [];

        $openTrade = null;
        $tradeCount = 0;
        $minAllowedRatio = 1;
        $tradeSetupDetails = null;

        $allTrades = [];
        $openingMarkers[] = CommonHelpers::generateLabelPlot($data[10]['binance_timestamp'], 'blue', 'Init');

        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        DB::table('opened_trades')->truncate();


        $this->info('Starting....');
        // TRENDLINE

        $tradeSetupDetails = null;
        $openTrade = null;
        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        foreach ($data as $index => $candle) {

            if ($index < 10 || $index > (count($data) - 1)) {
                continue;
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
                    $profit = null;

                    if ($openTrade['direction'] === 'LONG') {
                        $profit = CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    } else {
                        $profit = -1 *  CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    }

                    $openTrade['closingPrice'] = $closingPrice;
                    $openTrade['symbol'] = $symbol;
                    $openTrade['interval'] = $interval;
                    $openTrade['closingTimestamp'] = $data[$index]['binance_timestamp'];
                    $openTrade['profit'] = $profit;


                    $openTrade['zones'] = $openTrade['zones'] ? json_encode($openTrade['zones']) : null;
                    $openTrade['fvg'] = $openTrade['profit'] ? json_encode($openTrade['fvg']) : null;
                    $openTrade['trendline'] = $openTrade['trendline'] ? json_encode($openTrade['trendline']) : null;

                    DB::table('opened_trades')->insert($openTrade);

                    $openTrade = null;
                } else {
                    continue;
                }
            }


            // TESTMODE
            $testModeOptions = [
                'data' => $data,
                'index' => $index,
                'data1hRaw' => $data1hRaw,
                'data4hRaw' => $data4hRaw,
                'zoneProcessing' => false,
                'enabledStrategies' => [
                    'TRENDLINE',
                    // 'DOUBLE_BREAKOUTS',
                    // 'FVG',
                    // 'AGGRESSIVE',
                ]
            ];

            if (!$tradeSetupDetails)
                $tradeSetupDetails = BTCUSDT::runTrader($testModeOptions);

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
        $this->info('TRENDLINE Completed');

        $tradeSetupDetails = null;
        $openTrade = null;
        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        // FVG
        foreach ($data as $index => $candle) {

            if ($index < 10 || $index > (count($data) - 1)) {
                continue;
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
                    $profit = null;


                    if ($openTrade['direction'] === 'LONG') {
                        $profit = CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    } else {
                        $profit = -1 *  CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    }

                    $openTrade['closingPrice'] = $closingPrice;
                    $openTrade['symbol'] = $symbol;
                    $openTrade['interval'] = $interval;
                    $openTrade['closingTimestamp'] = $data[$index]['binance_timestamp'];
                    $openTrade['profit'] = $profit;


                    $openTrade['zones'] = $openTrade['zones'] ? json_encode($openTrade['zones']) : null;
                    $openTrade['fvg'] = $openTrade['profit'] ? json_encode($openTrade['fvg']) : null;
                    $openTrade['trendline'] = $openTrade['trendline'] ? json_encode($openTrade['trendline']) : null;

                    DB::table('opened_trades')->insert($openTrade);

                    $openTrade = null;
                } else {
                    continue;
                }
            }


            // TESTMODE
            $testModeOptions = [
                'data' => $data,
                'index' => $index,
                'data1hRaw' => $data1hRaw,
                'data4hRaw' => $data4hRaw,
                'zoneProcessing' => false,
                'enabledStrategies' => [
                    // 'TRENDLINE',
                    // 'DOUBLE_BREAKOUTS',
                    'FVG',
                    // 'AGGRESSIVE',
                ]
            ];

            if (!$tradeSetupDetails)
                $tradeSetupDetails = BTCUSDT::runTrader($testModeOptions);

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
        $this->info('FVG Completed');

        $tradeSetupDetails = null;
        $openTrade = null;
        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        // DOUBLE BREAKOUT
        foreach ($data as $index => $candle) {

            if ($index < 10 || $index > (count($data) - 1)) {
                continue;
            }
            BTCUSDT::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);


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
                    $profit = null;


                    if ($openTrade['direction'] === 'LONG') {
                        $profit = CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    } else {
                        $profit = -1 *  CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    }

                    $openTrade['closingPrice'] = $closingPrice;
                    $openTrade['symbol'] = $symbol;
                    $openTrade['interval'] = $interval;
                    $openTrade['closingTimestamp'] = $data[$index]['binance_timestamp'];
                    $openTrade['profit'] = $profit;


                    $openTrade['zones'] = $openTrade['zones'] ? json_encode($openTrade['zones']) : null;
                    $openTrade['fvg'] = $openTrade['profit'] ? json_encode($openTrade['fvg']) : null;
                    $openTrade['trendline'] = $openTrade['trendline'] ? json_encode($openTrade['trendline']) : null;

                    DB::table('opened_trades')->insert($openTrade);

                    $openTrade = null;
                } else {
                    continue;
                }
            }


            // TESTMODE
            $testModeOptions = [
                'data' => $data,
                'index' => $index,
                'data1hRaw' => $data1hRaw,
                'data4hRaw' => $data4hRaw,
                'zoneProcessing' => false,
                'enabledStrategies' => [
                    // 'TRENDLINE',
                    'DOUBLE_BREAKOUTS',
                    // 'FVG',
                    // 'AGGRESSIVE',
                ]
            ];

            if (!$tradeSetupDetails)
                $tradeSetupDetails = BTCUSDT::runTrader($testModeOptions);

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
        $this->info('DOUBLE BREAKOUT Completed');

        $tradeSetupDetails = null;
        $openTrade = null;
        CommonHelpers::flushZones($symbol);
        DB::table('trade_setup_details')->truncate();
        // AGGRESSIVE
        foreach ($data as $index => $candle) {

            if ($index < 10 || $index > (count($data) - 1)) {
                continue;
            }
            BTCUSDT::updateZonesInDb($data, $index, $data1hRaw, $data4hRaw, $interval, $symbol);


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
                    $profit = null;


                    if ($openTrade['direction'] === 'LONG') {
                        $profit = CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    } else {
                        $profit = -1 *  CommonHelpers::getPercentDiff($openTrade['openingPrice'], $closingPrice, true);
                    }

                    $openTrade['closingPrice'] = $closingPrice;
                    $openTrade['symbol'] = $symbol;
                    $openTrade['interval'] = $interval;
                    $openTrade['closingTimestamp'] = $data[$index]['binance_timestamp'];
                    $openTrade['profit'] = $profit;


                    $openTrade['zones'] = $openTrade['zones'] ? json_encode($openTrade['zones']) : null;
                    $openTrade['fvg'] = $openTrade['profit'] ? json_encode($openTrade['fvg']) : null;
                    $openTrade['trendline'] = $openTrade['trendline'] ? json_encode($openTrade['trendline']) : null;

                    DB::table('opened_trades')->insert($openTrade);

                    $openTrade = null;
                } else {
                    continue;
                }
            }


            // TESTMODE
            $testModeOptions = [
                'data' => $data,
                'index' => $index,
                'data1hRaw' => $data1hRaw,
                'data4hRaw' => $data4hRaw,
                'zoneProcessing' => false,
                'enabledStrategies' => [
                    // 'TRENDLINE',
                    // 'DOUBLE_BREAKOUTS',
                    // 'FVG',
                    'AGGRESSIVE',
                ]
            ];

            if (!$tradeSetupDetails)
                $tradeSetupDetails = BTCUSDT::runTrader($testModeOptions);

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
        $this->info('AGGRESSIVE Completed');


        dd("Completed");
    }
}
