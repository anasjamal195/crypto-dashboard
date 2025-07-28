<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\DivergenceStrategyService;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\HyperLiquidApiService;
use App\Services\IdealTradeService;
use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceSafeMode;
use App\Services\InternalTrader\ReportServiceSafeModeMacdSwing;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use App\Services\ReportService\LongReportService;
use App\Services\SupportResistanceAnalyzer;
use App\Services\TradingGapAnalyzer;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BinanceController extends Controller
{

    public function deleteCoinReport()
    {

        if (request('current_formula_only')) {
            $formula = request('formula');
            if (!$formula)
                return redirect()->back()->withError('Error Deleting Report!');
            DB::table('coin_reports')->where('formula', $formula)->delete();
            DB::table('formula_details')->where('formula', $formula)->delete();
            return redirect()->route('coinReport', 'FUTURE')->withSuccess('Current Report Deleted Successfully!');
        } else if (request('incomplete_only')) {

            $formulas =   DB::table('formula_details')->where('progress', '!=', 100)->pluck('formula');
            DB::table('coin_reports')->whereIn('formula', $formulas)->delete();
            DB::table('formula_details')->whereIn('formula', $formulas)->delete();
            return redirect()->route('coinReport', 'FUTURE')->withSuccess(count($formulas) . " Reports Deleted Successfully!");
        } else if (request('delete_all')) {
            $count =   DB::table('formula_details')->get()->count();
            DB::table('coin_reports')->truncate();
            DB::table('formula_details')->truncate();
            return redirect()->route('coinReport', 'FUTURE')->withSuccess($count . " Reports Deleted Successfully!");
        } else {
            return redirect()->back()->withError('Error Deleting Report!');
        }
    }
    public function volumeSignal()
    {
        $pageSlug = 'Volume Signal Dashboard';
        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 100);
        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true, null, $limit);

        $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);

        // $coinData = [];
        return view('volume-signals.index', compact('volumeSignals', 'symbol', 'pageSlug', 'coinData'));
    }
    public function getCoinReport($market, Request $request)
    {

        $stopLoss = $request->input('stopLoss') ?? 1;
        $position = $request->input('position');
        $formula = $request->input('formula');

        $tableName = $request->input('safe_mode_view') ? 'coin_reports_safe_mode' : 'coin_reports';





        $formulaDetails = DB::table('formula_details')->where('formula', $formula)->first();
        $basicStats = [];
        $colors = [
            'MACD' => [
                'LONG'  => '#2ecc71',  // Green
                'SHORT' => '#e74c3c',  // Red
                'LOSS'  => '#f1c40f',  // Yellow
            ],
            'SR' => [
                'LONG'  => '#17a2b8',  // Teal Blue
                'SHORT' => '#6610f2',  // Indigo
                'LOSS'  => '#6c757d',  // Steel Gray
            ],
            'default' => [
                'LONG'  => '#2ecc71',  // Green
                'SHORT' => '#e74c3c',  // Red
                'LOSS'  => '#f1c40f',  // Yellow
            ]
        ];
        // Return early with default values if no formula provided
        if (!$request->has('formula') || !$formulaDetails) {
            return view('CoinReports.coin-report', [
                'tradeData'          => [],
                'profitableTrades'   => 0,
                'profitsTotal'       => 0,
                'timelineData'       => [],
                'tradesAbove1h'      => 0,
                'maxNearbyTrades'    => 0,
                'averageDuration'    => 0,
                'stopLossesTotal'    => 0,
                'stopLoss'           => 0,
                'stopLossesTrades'   => 0,
                'pageSlug'           => 'Coin Report',
                'interval'           => '5m',
                'market'             => $market,
                'liquidatedSymbols'  => [],
                'liquidatedIntervals' => [],
                'accuracyThreshold' => 0,
                'liquidatedMarkets'  => [],
                'tpLimit' => 0.4,



            ]);

            return view('CoinReports.coin-report', [
                'tradeData'          => [],
                'profitableTrades'   => 0,
                'profitsTotal'       => 0,
                'timelineData'       => [],
                'reportAnalysis'       => [],
                'tradesAbove1h'      => 0,
                'tradesAbove1hLoss'  => 0,
                'tradesAbove1hProfit' => 0,
                'maxNearbyTrades'    => [],
                'averageDuration'    => 0,
                'stopLossesTotal'    => 0,
                'stopLoss'           => 0,
                'stopLossesTrades'   => 0,
                'pageSlug'           => $pageSlug,
                'interval'           => $interval,
                'market'             => $market,
                'liquidatedSymbols'  => [],
                'liquidatedIntervals' => [],
                'liquidatedMarkets'  => [],
                'tpLimit'  => 0.4,
                'firstTradeAverageTime'  =>  0,

                // RSI Stats
                'rsiAbove40Profitable' => 0,
                'rsiAbove40Loss' => 0,
                'rsiAbove40Total' => 0,
                'rsiBelow40Profitable' => 0,
                'rsiBelow40Loss' => 0,
                'rsiBelow40Total' => 0,
                'rsiLimit' => 0,
                'tradesBelowTP' => 0,

                'timelineDataSkipped' => [],

                'bullishOpenings' => 0,
                'bullishOpeningsProfit' => 0,
                'bullishOpeningsLoss' => 0,
                'berishOpenings' => 0,
                'berishOpeningsProfit' => 0,
                'berishOpeningsLoss' => 0,
                'accuracyThreshold' => 0,
                'accuracyThresholdLow' => 0,

                'instantOpenings' => 0,
                'instantOpeningsProfit' => 0,
                'instantOpeningsLoss' => 0,
                'instantOpeningsSymbols' => [],
                'instantAverageTime' =>  0,
                'instantAverageTimeProfit' =>  0,
                'instantAverageTimeLoss' =>  0,

                'wrProfitable' => 0,
                'wrLoss' => 0,
                'wrBelowProfitable' => 0,
                'wrBelowLoss' => 0,
                'wrBelowTotal' => 0,
                'wrLimit' => 0,
                'wrTotal' => 0,

                'earlyClosedProfitable' => 0,
                'earlyClosedLoss' => 0,
                'earlyClosedTotal' => 0,

                // Opened Symbols Stats
                'openSymbols' => [],
                'tradeArr' => [],

                // Trend Analysis Data
                'dataTrendReference' => [],
                'trendReferenceSymbol' => '',
                'trendReferenceInterval' => '',

                'dataTrendReferenceActual' => [],
                'trendReferenceSymbolActual' => '',
                'trendReferenceIntervalActual' => '',
                'baseAccuracy' => 0,
                'baseFrequency' => 0,
                'timelineColors' => $colors,
            ]);
        }

        // Build base query with common filters
        $baseQuery = DB::table($tableName)->where('market', $market);

        if ($position) {
            $baseQuery->where('position', $position);
        }

        if ($formula) {
            $baseQuery->where('formula', $formula);
        }

        // To filter only completed trades
        $baseQuery->whereNotNull('sellingCandle');

        // Clone the base query for reuse
        $tradeDataQuery = clone $baseQuery;

        // Get aggregated trade data
        $tradeData = $tradeDataQuery->select(
            'symbol',
            'formula',
            'position',
            'interval',
            DB::raw('COUNT(*) as total_entries'),
            DB::raw('SUM(profit) as total_profit'),
            DB::raw('SUM(CASE WHEN profit < 0 THEN 1 ELSE 0 END) as number_of_sl'),
            DB::raw('AVG(profit) as average_profit'),
            DB::raw('AVG(duration) as average_duration'),
            DB::raw('SUM(duration) as total_duration'),
            DB::raw('MAX(profit) as max_profit'),
            DB::raw('MIN(profit) as min_profit'),
            DB::raw('MAX(lowestPricePercentage) as max_lowestPrice'),
            DB::raw('MIN(lowestPricePercentage) as min_lowestPrice'),
            DB::raw('MAX(created_at) as last_updated')
        )

            ->groupBy('symbol', 'position', 'formula', 'interval')
            ->orderBy('total_entries', 'DESC')
            ->orderBy('last_updated', 'DESC')
            ->get();

        // Get average duration for profitable trades under 60 minutes
        $averageDuration = (clone $baseQuery)
            ->where('profit', '>', 0)
            ->where('duration', '<=', 60)
            ->average('duration');

        // Get max nearby trades
        $maxNearbyTrades = (clone $baseQuery)
            ->selectRaw("
                DATE_FORMAT(
                    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.timestampReadable')), '%Y-%m-%d %H:%i:%s'),
                    '%Y-%m-%d %H:%i:00'
                ) - INTERVAL (MINUTE(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.timestampReadable')), '%Y-%m-%d %H:%i:%s')) % 5) MINUTE AS time_interval,
                COUNT(*) as entry_count
            ")
            ->groupBy('time_interval')
            ->orderBy('entry_count', 'DESC')
            ->first();

        // Extract interval from tradeData
        $interval = count($tradeData) ? $tradeData[0]->interval : '5m';
        $pageSlug = 'CoinReport' . $market;

        // Consolidated statistics queries
        $statsQuery = clone $baseQuery;
        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_trades,
            SUM(CASE WHEN profit > 0 THEN 1 ELSE 0 END) as profitable_trades,
            SUM(CASE WHEN profit > 0 THEN profit ELSE 0 END) as profits_total,
            COUNT(CASE WHEN duration > 60 THEN 1 END) as trades_above_1h,
            COUNT(CASE WHEN profit > 0 AND duration > 60 THEN 1 END) as trades_above_1h_profit,
            COUNT(CASE WHEN profit < 0 AND duration > 60 THEN 1 END) as trades_above_1h_loss,
            COUNT(CASE WHEN profit < 0 THEN 1 END) as stop_losses_trades,
            SUM(CASE WHEN profit < 0 THEN ABS(profit) ELSE 0 END) as stop_losses_total
        ')->first();

        // Liquidated coins query
        $liquidatedCoins = (clone $baseQuery)
            ->select('symbol', 'interval', 'market')
            ->whereRaw('liquidationPrice >= lowestPrice')
            ->get();

        // Extract unique data from liquidated coins
        $liquidatedSymbols = json_decode(json_encode($liquidatedCoins->pluck('symbol')->unique()), true);
        $liquidatedIntervals = json_decode(json_encode($liquidatedCoins->pluck('interval')->unique()), true);
        $liquidatedMarkets = json_decode(json_encode($liquidatedCoins->pluck('market')->unique()), true);

        // Get all trades for analysis in a single query
        $tradeArr = (clone $baseQuery)->get();
        $tradeArr = json_decode(json_encode($tradeArr), true);

        $reportAnalysis = !empty($tradeArr) ? CommonHelpers::analyzeTradeReport($tradeArr) : [];

        // Trend Analysis Data, Only for back testing
        $formulaConfig = $formulaDetails ? json_decode($formulaDetails->report_config, true) : null;



        $trendReferenceSymbol = ($formulaConfig && $formulaConfig['trendReferenceSymbol']) ? $formulaConfig['trendReferenceSymbol'] : 'BTCUSDT';

        // $trendReferenceInterval = ($formulaConfig && $formulaConfig['trendReferenceInterval']) ? $formulaConfig['trendReferenceInterval'] : '1h';
        $trendReferenceInterval = ($formulaConfig && $formulaConfig['trendReferenceInterval']) ? $formulaConfig['trendReferenceInterval'] : '1h';

        $date = new DateTime($formulaDetails->created_at, new DateTimeZone('Asia/Karachi'));
        $date->setTimezone(new DateTimeZone('UTC')); // convert to UTC
        $createdTimestampMs = $date->getTimestamp() * 1000; // get UTC timestamp in milliseconds

        $isHyperliquid = ($formulaConfig && isset($formulaConfig['exchange']) && $formulaConfig['exchange'] === 'hyperliquid');
        $startUnix = ($formulaConfig && $formulaConfig['startUnix'])
            ? $formulaConfig['startUnix']
            : ($createdTimestampMs - (CommonHelpers::$binanceIntervals[$interval] * 60 * 1000 * ($isHyperliquid ? 5000 : 1000)));

        $endUnix = ($formulaConfig && $formulaConfig['endUnix'])
            ? $formulaConfig['endUnix']
            : $createdTimestampMs;

        $intervalMs = CommonHelpers::$binanceIntervals[$trendReferenceInterval] * 60 * 1000; // Interval in ms


        $candleCount = intval(($endUnix - $startUnix) / $intervalMs);


        $dataTrendReference =
            $isHyperliquid ?
            HyperLiquidApiService::getCandleStickData($trendReferenceSymbol, $trendReferenceInterval, $candleCount, $startUnix, 'FUTURE')
            :
            BinanceApiService::getCandleStickData($trendReferenceSymbol, $trendReferenceInterval, $candleCount, $startUnix, 'FUTURE');


        // Trend reference settings for actual interval
        $trendReferenceSymbolActual = count($tradeData) ? $tradeData[0]->symbol : 'BTCUSDT';
        $dataTrendReferenceActual = $isHyperliquid ?
            HyperLiquidApiService::getCandleStickData($trendReferenceSymbolActual, $interval, 5000, $startUnix, 'FUTURE')
            :
            BinanceApiService::getCandleStickData($trendReferenceSymbolActual, $interval, 1000, $startUnix, 'FUTURE');




        // Initialize statistics counters
        $rsiLimit = 65;
        $wrLimit = -10;
        $accuracyThreshold = 90;
        $accuracyThresholdLow = 80;

        $rsiAbove40Profitable = $rsiAbove40Loss = $rsiAbove40Total = 0;
        $rsiBelow40Profitable = $rsiBelow40Loss = $rsiBelow40Total = 0;

        $tradesBelowTP = 0;
        $tpLimit = 0.8;

        $bb_lower_count = $bb_lower_profit = $bb_lower_loss = 0;
        $bb_upper_count = $bb_upper_profit = $bb_upper_loss = 0;

        $bullishOpenings = $bullishOpeningsProfit = $bullishOpeningsLoss = 0;
        $berishOpenings = $berishOpeningsProfit = $berishOpeningsLoss = 0;

        $instantOpenings = $instantOpeningsProfit = $instantOpeningsLoss = 0;
        $instantOpeningsSymbols = [];
        $instantAverageTime = $instantAverageTimeProfit = $instantAverageTimeLoss = 0;

        $wrProfitable = $wrLoss = $wrBelowProfitable = $wrBelowLoss = 0;

        $bbUpTrades = $bbUpProfit = $bbUpLoss = 0;


        $profitableTotal = 0;
        $lossTotal = 0;
        $profitableChangeSum = 0;
        $lossChangeSum = 0;

        $earlyClosedProfitable = $earlyClosedLoss = 0;


        // Average first trade time



        $startingTimestamp = $startUnix;
        $firstTradeTimestamp = !empty($tradeArr) ? json_decode($tradeArr[0]['buyingCandle'], true)['binance_timestamp'] : time() * 1000;
        $firstTradeAverageTime = ($firstTradeTimestamp - $startingTimestamp) / (1000 * 60);

        // dd($firstTradeAverageTime,$firstTradeTimestamp,$startingTimestamp);
        // Process trades for statistics in a single loop instead of multiple queries
        foreach ($tradeArr as $trade) {
            $buyingCandle = json_decode($trade['buyingCandle'], true);
            $confirmCandle = json_decode($trade['confirmCandle'], true);
            $previousCandle = json_decode($trade['previousCandle'], true);
            $isProfit = $trade['profit'] > 0;





            $currentTradeTime = ($buyingCandle['binance_timestamp'] - $startingTimestamp) / (1000 * 60);
            $firstTradeAverageTime = $firstTradeAverageTime < $currentTradeTime ? $firstTradeAverageTime : $currentTradeTime;



            if ($trade['closed_early']) {
                $isProfit ? $earlyClosedProfitable++ : $earlyClosedLoss++;
            }
            if ($buyingCandle['trendDetails']) {

                $trend = json_decode($buyingCandle['trendDetails'], true);
                // dd($trend);

                if ($isProfit) {
                    $profitableTotal++;
                    $profitableChangeSum += $trend['strength'];
                } else {
                    $lossTotal++;
                    $lossChangeSum += $trend['strength'];;
                }
            }











            // // Williams %R analysis
            if ($buyingCandle['trendDetails']) {
                $trend = json_decode($buyingCandle['trendDetails'], true);


                $upperWick = $buyingCandle['high'] - max($buyingCandle['open'], $buyingCandle['close']);
                $lowerWick =  min($buyingCandle['open'], $buyingCandle['close']) - $buyingCandle['low'];
                $solidRegion = CommonHelpers::getCandleSolidRegion($buyingCandle);
                $lowerWick = CommonHelpers::getCandleWick($buyingCandle, 'lower');


                $lowerWickPercentage = ($lowerWick / max(0.00001, $solidRegion)) * 100;
                if (
                    $lowerWickPercentage > 0.5

                ) {

                    // dd($trend);
                    $isProfit ? $bbUpProfit++ : $bbUpLoss++;
                    $bbUpTrades++;
                }
            }





            // Williams %R analysis
            if ($buyingCandle['wr'] > $wrLimit) {
                $isProfit ? $wrProfitable++ : $wrLoss++;
            } else {
                $isProfit ? $wrBelowProfitable++ : $wrBelowLoss++;
            }



            // RSI analysis
            if ($buyingCandle['rsi6'] >= $rsiLimit) {
                $isProfit ? $rsiAbove40Profitable++ : $rsiAbove40Loss++;
                $rsiAbove40Total++;
            } else {
                $isProfit ? $rsiBelow40Profitable++ : $rsiBelow40Loss++;
                $rsiBelow40Total++;
            }

            // Take profit analysis
            if ($isProfit && $trade['profit'] < $tpLimit) {
                $tradesBelowTP++;
            }

            // Bollinger bands analysis
            if (min($buyingCandle['open'], $buyingCandle['close']) > $buyingCandle['bb_middle']) {
                $bb_upper_count++;
                $isProfit ? $bb_upper_profit++ : $bb_upper_loss++;
            }

            if ($buyingCandle['open'] < $buyingCandle['bb_middle'] && $buyingCandle['close'] > $buyingCandle['bb_middle']) {
                $bb_lower_count++;
                $isProfit ? $bb_lower_profit++ : $bb_lower_loss++;
            }

            // Candle pattern analysis
            if ($trade['position'] === 'LONG') {
                if ($buyingCandle['per'] > 0) {
                    $bullishOpenings++;
                    $isProfit ? $bullishOpeningsProfit++ : $bullishOpeningsLoss++;
                }

                if ($buyingCandle['per'] < 0) {
                    $berishOpenings++;
                    $isProfit ? $berishOpeningsProfit++ : $berishOpeningsLoss++;
                }
            }

            // if($confirmCandle['binance_timestamp'] ==  $buyingCandle['binance_timestamp']){
            //     $instantOpenings++;
            //     $isProfit ? $instantOpeningsProfit++ : $instantOpeningsLoss++;
            //     $isProfit ? null : $instantOpeningsSymbols[]= $trade['symbol'];
            // }
        }
        // dd($profitableChangeSum / $profitableTotal, $lossChangeSum / $lossTotal, $profitableTotal, $lossTotal);
        // dd("Total:", $bbUpTrades, "Profits:", $bbUpProfit, "Losses:", $bbUpLoss, "Accuracy: ", ($bbUpProfit / $bbUpTrades) * 100);


        // Prepare timeline data
        $timelineData = array_map(function ($trade) use ($stopLoss, $colors) {
            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);
            $trade['sellingCandle'] = json_decode($trade['sellingCandle'], true);
            $color = '';
            // Color mapping




            $tag = $trade['tagName'];
            $position = $trade['position'];
            $profit = $trade['profit'];

            // Use specific tag if exists, else fallback
            $tagKey = array_key_exists($tag, $colors) ? $tag : 'default';

            if ($profit < 0) {
                $color = $colors[$tagKey]['LOSS'];
            } elseif ($position === 'LONG') {
                $color = $colors[$tagKey]['LONG'];
            } elseif ($position === 'SHORT') {
                $color = $colors[$tagKey]['SHORT'];
            }



            return [
                'symbol' => $trade['symbol'] . '( ' . $trade['position'] . ' )',
                'startTime' => $trade['buyingCandle']['timestampReadable'],
                'endTime' => $trade['sellingCandle']['timestampReadable'],
                'color' => $color,
                'id' => $trade['id'],
                'buyingCandle' => $trade['buyingCandle'],
            ];
        }, $tradeArr);

        $confirmedTrades = DB::table('confirmed_trades')->where('formula', $formula)->get();


        // foreach ($confirmedTrades as $confirmTrade) {

        //     $timestampMillis = $confirmTrade->confirm_candle_timestamp;


        //     // Convert to Carbon instance in Asia/Karachi timezone
        //     $timestamp = Carbon::createFromTimestampMs($timestampMillis)->setTimezone('Asia/Karachi');

        //     // Format as SQL timestamp (Y-m-d H:i:s)
        //     $sqlTimestamp = $timestamp->toDateTimeString();

        //     // Add 5 minutes
        //     $sqlTimestampPlus5Min = $timestamp->copy()->addMinutes(5)->toDateTimeString();
        //     $timelineData[] = [
        //         'symbol' => $confirmTrade->coin_name . '( ' . $confirmTrade->type . ' )',
        //         'startTime' => $sqlTimestamp,
        //         'endTime' => $sqlTimestampPlus5Min,
        //         'color' => '#ffffff',
        //         'id' => $confirmTrade->ict_id,
        //         'buyingCandle' => null,
        //     ];
        // }



        $tradeArrSkipped = DB::table('skipped_trades')->where('formula', $formula)->get();
        $tradeArrSkipped = json_decode(json_encode($tradeArrSkipped), true);
        $timelineDataSkipped = array_map(function ($trade) {

            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);
            $trade['sellingCandle'] = json_decode($trade['sellingCandle'], true);
            $trade['skipping_reasons'] = json_decode($trade['skipping_reasons'], true);
            $color = 'orange';

            return [
                'symbol' => $trade['symbol'],
                'startTime' => $trade['start_time'],
                'endTime' => $trade['end_time'],
                'color' => $trade['color'],
                'id' => $trade['id'],
                'buyingCandle' => $trade['buyingCandle'],
                'skipping_reasons' => $trade['skipping_reasons'],
            ];
        }, $tradeArrSkipped);
        // dd($timelineDataSkipped[0]);


        $openTradesQuery =  DB::table($tableName)->where('market', $market);

        if ($position) {
            $openTradesQuery->where('position', $position);
        }

        if ($formula) {
            $openTradesQuery->where('formula', $formula);
        }

        // To filter only completed trades
        $openTradesQuery->whereNull('sellingCandle');

        $openSymbols = $openTradesQuery->pluck('symbol');

        // dd($firstTradeAverageTime);

        // dd($dataTrendReference);


        $timeWiseTradeCount = [];
        $timeWiseTradeCountProfitable = [];
        $timeWiseTradeCountProfitableQuick = [];
        $timeWiseTradeCountLoss = [];
        $timeWiseTradeCountSkipped = [];

        foreach ($tradeArr as $trade) {

            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);
            $trade['sellingCandle'] = json_decode($trade['sellingCandle'], true);

            if (isset($timeWiseTradeCount[$trade['buyingCandle']['binance_timestamp']])) {
                $timeWiseTradeCount[$trade['buyingCandle']['binance_timestamp']] += 1;
            } else {
                $timeWiseTradeCount[$trade['buyingCandle']['binance_timestamp']] = 1;
            }



            // Profitable Trades Count
            if ($trade['profit'] > 0) {
                if (isset($timeWiseTradeCountProfitable[$trade['buyingCandle']['binance_timestamp']])) {
                    $timeWiseTradeCountProfitable[$trade['buyingCandle']['binance_timestamp']] += 1;
                    if ($trade['duration'] <= 30) {
                        if (isset($timeWiseTradeCountProfitableQuick[$trade['buyingCandle']['binance_timestamp']])) {
                            $timeWiseTradeCountProfitableQuick[$trade['buyingCandle']['binance_timestamp']] += 1;
                        } else {
                            $timeWiseTradeCountProfitableQuick[$trade['buyingCandle']['binance_timestamp']] = 1;
                        }
                    }
                } else {
                    $timeWiseTradeCountProfitable[$trade['buyingCandle']['binance_timestamp']] = 1;
                }
            } else {
                if (isset($timeWiseTradeCountLoss[$trade['buyingCandle']['binance_timestamp']])) {
                    $timeWiseTradeCountLoss[$trade['buyingCandle']['binance_timestamp']] += 1;
                } else {
                    $timeWiseTradeCountLoss[$trade['buyingCandle']['binance_timestamp']] = 1;
                }
            }
        }

        // For Skipped Trades
        foreach ($tradeArrSkipped as $trade) {

            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);

            if (isset($timeWiseTradeCountSkipped[$trade['buyingCandle']['binance_timestamp']])) {
                $timeWiseTradeCountSkipped[$trade['buyingCandle']['binance_timestamp']] += 1;
            } else {
                $timeWiseTradeCountSkipped[$trade['buyingCandle']['binance_timestamp']] = 1;
            }
        }

        $progressionDetailsLONG = request('safe_mode_view') ? ReportServiceSafeMode::getProgressionDetails($formula, 'LONG', $endUnix) : ReportService::getProgressionDetails($formula, 'LONG', $endUnix);
        $progressionDetailsSHORT = request('safe_mode_view') ? ReportServiceSafeMode::getProgressionDetails($formula, 'SHORT', $endUnix) : ReportService::getProgressionDetails($formula, 'SHORT', $endUnix);



        // Plotting them in current trend data
        foreach ($dataTrendReferenceActual as &$candle) {
            if (isset($timeWiseTradeCount[$candle['binance_timestamp']])) {
                $candle['total_trades'] = $timeWiseTradeCount[$candle['binance_timestamp']];
            } else {
                $candle['total_trades'] = 0;
            }

            // Profitable
            if (isset($timeWiseTradeCountProfitable[$candle['binance_timestamp']])) {
                $candle['total_trades_profitable'] = $timeWiseTradeCountProfitable[$candle['binance_timestamp']];
            } else {
                $candle['total_trades_profitable'] = 0;
            }

            // Loss
            if (isset($timeWiseTradeCountLoss[$candle['binance_timestamp']])) {
                $candle['total_trades_loss'] = $timeWiseTradeCountLoss[$candle['binance_timestamp']];
            } else {
                $candle['total_trades_loss'] = 0;
            }

            // Skipped
            if (isset($timeWiseTradeCountSkipped[$candle['binance_timestamp']])) {
                $candle['total_trades_skipped'] = $timeWiseTradeCountSkipped[$candle['binance_timestamp']];
            } else {
                $candle['total_trades_skipped'] = 0;
            }


            $candle['accuracy_long'] = request('safe_mode_view') ? ReportServiceSafeMode::parseAccuracy($progressionDetailsLONG, $candle['binance_timestamp'], 6) : ReportService::parseAccuracy($progressionDetailsLONG, $candle['binance_timestamp'], 6);
            $candle['accuracy_short'] =  request('safe_mode_view') ? ReportServiceSafeMode::parseAccuracy($progressionDetailsSHORT, $candle['binance_timestamp'], 6) : ReportService::parseAccuracy($progressionDetailsSHORT, $candle['binance_timestamp'], 6);


            $profitLong = ReportService::parseProfit($progressionDetailsLONG, $candle['binance_timestamp']);
            $profitShort = ReportService::parseProfit($progressionDetailsSHORT, $candle['binance_timestamp']);

            // NET Profits Calculation

            $candle['profits_short'] = $profitShort;
            $candle['profits_long'] = $profitLong;
            $candle['profits_total'] = $profitLong + $profitShort;
        }



        $analyzer = new TradingGapAnalyzer();
        $result = $analyzer->findMaxTradingGap($timeWiseTradeCountProfitable, 0, 0);

        // dd($result);


        $baseFrequency = 0;
        $baseAccuracy = 0;
        if ($formulaConfig && isset($formulaConfig['isBaseReport']) && isset($formulaConfig['baseReportFormula'])) {


            $profits = DB::table($tableName)->where('formula', $formulaConfig['baseReportFormula'])->where('profit', '>', 0)->count();
            $total = DB::table($tableName)->where('formula', $formulaConfig['baseReportFormula'])->count();

            if ($total) {
                $baseAccuracy = ($profits / $total) * 100;
                $baseFrequency = $total;
            }
        }




        // $basicStats = [

        //     [
        //         'heading' => 'Basic Stats',
        //         'rows' => [
        //             [
        //                 'name' => 'Below TP',
        //                 'value' => $tradesBelowTP,
        //                 'description' => "Trades that closed early below $tpLimit %",
        //             ],
        //             [
        //                 'name' => '1h+ Duration',
        //                 'value' => $tradesBelowTP,
        //                 'description' => "Trades that closed early below $tpLimit %",
        //             ],
        //         ]
        //     ]


        // ];





        // SR Formula Stat

        $totalProfitsTradesSR = $totalLossesTradesSR = $totalProfitsSR = $totalLossesSR = $totalTradesSR = $grandTotalSR = $totalFeeSR = $accuracySR = 0;

        $totalProfitsTradesMACD  =  $totalLossesTradesMACD  = $totalProfitsMACD = $totalLossesMACD =  $totalTradesMACD = $grandTotalMACD = $totalFeeMACD = $accuracyMACD = 0;


        foreach ($tradeArr as $trade) {

            $isProfit = $trade['profit'] > 0;
            // SR Stats

            if ($trade['tagName'] === 'SR') {
                $isProfit ?
                    $totalProfitsTradesSR++ :
                    $totalLossesTradesSR++;



                $isProfit ?
                    $totalProfitsSR += $trade['profit'] :
                    $totalLossesSR += $trade['profit'];

                $grandTotalSR += $trade['profit'];
            }




            if ($trade['tagName'] === 'MACD') {

                $isProfit ?
                    $totalProfitsTradesMACD++ :
                    $totalLossesTradesMACD++;


                $isProfit ?
                    $totalProfitsMACD += $trade['profit'] :
                    $totalLossesMACD += $trade['profit'];

                $grandTotalMACD += $trade['profit'];
            }
        }

        $totalTradesMACD = $totalProfitsTradesMACD + $totalLossesTradesMACD;
        $totalTradesSR = $totalProfitsTradesSR + $totalLossesTradesSR;

        $totalFeeSR = $totalTradesSR * 0.15;
        $totalFeeMACD = $totalTradesMACD * 0.15;


        $accuracySR = $totalTradesSR ? round(($totalProfitsTradesSR / $totalTradesSR) * 100, 2) : 0;
        $accuracyMACD = $totalTradesMACD ? round(($totalProfitsTradesMACD / $totalTradesMACD) * 100, 2) : 0;









        // Return the view with consolidated data
        return view('CoinReports.coin-report', [
            'tradeData'          => $tradeData,
            'profitableTrades'   => $stats->profitable_trades,
            'profitsTotal'       => $stats->profits_total,
            'timelineData'       => $timelineData,
            'reportAnalysis'       => $reportAnalysis,
            'tradesAbove1h'      => $stats->trades_above_1h,
            'tradesAbove1hLoss'  => $stats->trades_above_1h_loss,
            'tradesAbove1hProfit' => $stats->trades_above_1h_profit,
            'maxNearbyTrades'    => $maxNearbyTrades,
            'averageDuration'    => $averageDuration,
            'stopLossesTotal'    => $stats->stop_losses_total,
            'stopLoss'           => $stopLoss,
            'stopLossesTrades'   => $stats->stop_losses_trades,
            'pageSlug'           => $pageSlug,
            'interval'           => $interval,
            'market'             => $market,
            'liquidatedSymbols'  => $liquidatedSymbols,
            'liquidatedIntervals' => $liquidatedIntervals,
            'liquidatedMarkets'  => $liquidatedMarkets,
            'tpLimit'  => $tpLimit,
            'firstTradeAverageTime'  => $startingTimestamp ? $firstTradeAverageTime : 0,

            // RSI Stats
            'rsiAbove40Profitable' => $rsiAbove40Profitable,
            'rsiAbove40Loss' => $rsiAbove40Loss,
            'rsiAbove40Total' => $rsiAbove40Total,
            'rsiBelow40Profitable' => $rsiBelow40Profitable,
            'rsiBelow40Loss' => $rsiBelow40Loss,
            'rsiBelow40Total' => $rsiBelow40Total,
            'rsiLimit' => $rsiLimit,
            'tradesBelowTP' => $tradesBelowTP,

            'timelineDataSkipped' => $timelineDataSkipped,

            'bullishOpenings' => $bullishOpenings,
            'bullishOpeningsProfit' => $bullishOpeningsProfit,
            'bullishOpeningsLoss' => $bullishOpeningsLoss,
            'berishOpenings' => $berishOpenings,
            'berishOpeningsProfit' => $berishOpeningsProfit,
            'berishOpeningsLoss' => $berishOpeningsLoss,
            'accuracyThreshold' => $accuracyThreshold,
            'accuracyThresholdLow' => $accuracyThresholdLow,

            'instantOpenings' => $instantOpenings,
            'instantOpeningsProfit' => $instantOpeningsProfit,
            'instantOpeningsLoss' => $instantOpeningsLoss,
            'instantOpeningsSymbols' => $instantOpeningsSymbols,
            'instantAverageTime' => $instantOpenings ? round($instantAverageTime / $instantOpenings) : 0,
            'instantAverageTimeProfit' => $instantOpeningsProfit ? round($instantAverageTimeProfit / $instantOpeningsProfit) : 0,
            'instantAverageTimeLoss' => $instantAverageTimeLoss ? round($instantAverageTimeLoss / $instantOpeningsLoss) : 0,

            'wrProfitable' => $wrProfitable,
            'wrLoss' => $wrLoss,
            'wrBelowProfitable' => $wrBelowProfitable,
            'wrBelowLoss' => $wrBelowLoss,
            'wrBelowTotal' => $wrBelowLoss + $wrBelowProfitable,
            'wrLimit' => $wrLimit,
            'wrTotal' => $wrLoss + $wrProfitable,

            'earlyClosedProfitable' => $earlyClosedProfitable,
            'earlyClosedLoss' => $earlyClosedLoss,
            'earlyClosedTotal' => $earlyClosedProfitable + $earlyClosedLoss,

            // Opened Symbols Stats
            'openSymbols' => $openSymbols,
            'tradeArr' => $tradeArr,

            // Trend Analysis Data
            'dataTrendReference' => $dataTrendReference,
            'trendReferenceSymbol' => $trendReferenceSymbol,
            'trendReferenceInterval' => $trendReferenceInterval,

            'dataTrendReferenceActual' => $dataTrendReferenceActual,
            'trendReferenceSymbolActual' => $trendReferenceSymbolActual,
            'trendReferenceIntervalActual' => $interval,
            'baseAccuracy' => $baseAccuracy,
            'baseFrequency' => $baseFrequency,
            'timelineColors' => $colors,


            // SR Stats
            'totalProfitsTradesSR'   => $totalProfitsTradesSR,
            'totalLossesTradesSR'   => $totalLossesTradesSR,


            'totalProfitsSR'   => $totalProfitsSR,
            'totalLossesSR'    => abs($totalLossesSR),
            'totalTradesSR'    => $totalTradesSR,
            'grandTotalSR'     => $grandTotalSR,
            'totalFeeSR'       => $totalFeeSR,
            'accuracySR'       => $accuracySR,

            // MACD Stats
            'totalProfitsTradesMACD'   => $totalProfitsTradesMACD,
            'totalLossesTradesMACD'   => $totalLossesTradesMACD,

            'totalProfitsMACD' => $totalProfitsMACD,
            'totalLossesMACD'  => abs($totalLossesMACD),
            'totalTradesMACD'  => $totalTradesMACD,
            'grandTotalMACD'   => $grandTotalMACD,
            'totalFeeMACD'     => $totalFeeMACD,
            'accuracyMACD'     => $accuracyMACD,

        ]);
    }
    public function getCoinReportConfirmedTrades($formula, Request $request)
    {


        $startTime = '2025-07-26 00:00:00';



        $userId = auth()->user() ? auth()->user()->id : 2;

        $confirmedTrades = DB::table('confirmed_trades')
            ->select(
                [
                    'exchange',
                    'coin_name',
                    'type',
                    'intention',
                    'candles_to_check',
                    'checkpoints',
                    'checkpoint_timestamp',
                ]
            )
            ->where('trade_confirmed', 0)->orderBy('checkpoints', 'DESC')->orderBy('checkpoint_timestamp', 'DESC')->get();
        $openedTrades = DB::table('live_trades_future_results')
            ->select(
                [
                    'exchange',
                    'orderId',
                    'symbol',
                    'side',
                    'position',
                    'type',
                    'amount',
                    'trade_status',
                    'qty',
                    'leverage',
                    'price',
                    'currentPrice',
                    'currentProfit',
                    'targetProfit',
                    'realizedPnl',
                    'formula',
                    'trade_acc',
                    'created_at',
                    'updated_at',
                ]
            )
            ->where('trade_acc', $userId)->where('trade_status', 'open')->where('created_at', '>=', $startTime)->where('type', 'open')->latest()->get();
        $closedTrades = DB::table('live_trades_future_results')
            ->select(
                [
                    'exchange',
                    'orderId',
                    'symbol',
                    'side',
                    'position',
                    'type',
                    'amount',
                    'trade_status',
                    'qty',
                    'leverage',
                    'price',
                    'currentPrice',
                    'currentProfit',
                    'targetProfit',
                    'realizedPnl',
                    'formula',
                    'trade_acc',
                    'created_at',
                    'updated_at',
                ]
            )->where('trade_acc', $userId)->where('trade_status', 'close')->where('created_at', '>=', $startTime)->where('type', 'open')->latest()->get();

        $tradeDetails = [
            'pendingOpening' => $confirmedTrades,
            'openedTrades' => $openedTrades,
            'closedTrades' => $closedTrades,
            'startTime' => $startTime,
        ];

        $tradeDetails = json_decode(json_encode($tradeDetails), true);








        return view('CoinReports.confirmed-trades', [
            'tradeDetails' => $tradeDetails,
            'pageSlug' => 'ConfirmedTrades',
        ]);
    }
    public function getCoinReportDetails($market, Request $request)
    {

        // Get the symbol from the request
        $symbol = $request->query('symbol');
        $interval = $request->query('interval');
        $position = $request->query('position');
        $formula = $request->query('formula');
        $stopLoss = $request->query('stopLoss') ?? 1;

        // Fetch the trades for the given symbol
        $trades = DB::table('coin_reports')
            ->where('symbol', $symbol)
            ->where('market', $market)
            ->where('formula', $formula)
            ->where('position', $position)
            ->where('interval', $interval)
            ->orderBy('id', 'ASC')
            ->get()
            ->map(function ($trade) {
                $trade->buyingCandle = json_decode($trade->buyingCandle);
                $trade->sellingCandle = json_decode($trade->sellingCandle);
                $trade->confirmCandle = json_decode($trade->confirmCandle);
                $trade->highestCandle = json_decode($trade->highestCandle);

                return $trade;
            });



        // Trend Analysis Data, Only for back testing
        $formulaDetails = DB::table('formula_details')->where('formula', $formula)->first();
        $formulaConfig = json_decode($formulaDetails->report_config, true);


        $limit = request('limit', 1000);
        $date = new DateTime($formulaDetails->created_at, new DateTimeZone('Asia/Karachi'));
        $date->setTimezone(new DateTimeZone('UTC')); // convert to UTC
        $createdTimestampMs = $date->getTimestamp() * 1000; // get UTC timestamp in milliseconds

        $isHyperliquid = ($formulaConfig && isset($formulaConfig['exchange']) && $formulaConfig['exchange'] === 'hyperliquid');
        $startUnix = ($formulaConfig && $formulaConfig['startUnix'])
            ? $formulaConfig['startUnix']
            : ($createdTimestampMs - (CommonHelpers::$binanceIntervals[$interval] * 60 * 1000 * ($isHyperliquid ? 5000 : $limit)));

        $endUnix = ($formulaConfig && $formulaConfig['endUnix'])
            ? $formulaConfig['endUnix']
            : $createdTimestampMs;


        // Fetching Base Candle Data
        // $startTime = $trades->first()->buyingCandle->binance_timestamp - (CommonHelpers::$binanceIntervals[$interval] * 100 * 60 * 1000);

        $data = $isHyperliquid ?
            HyperLiquidApiService::getCandleStickData($symbol, $interval, 5000, $startUnix, $market)
            :
            BinanceApiService::getCandleStickDataExtended($symbol, $interval, $limit, $startUnix, $market);

        foreach ($data as $index => &$candle) {

            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
        }


        if (!empty($data)) {
            // Determine the start and end time from the fetched candlestick data
            $startTime = $data[0]['timestamp'];
            $endTime = end($data)['timestamp'];

            // Fetch live trades from live_trades_future_results between start and end time
            $liveTrades = DB::table('live_trades_future_results')
                ->where('symbol', $symbol)

                ->where('formula', $formula)
                ->where('position', $position)

                ->whereBetween('created_at', [$startTime, $endTime])
                ->get();
            $liveTradesData  = DB::table('live_trades_future_results')
                ->where('symbol', $symbol)

                ->where('formula', $formula)
                ->where('position', $position)

                ->where('type', 'open')
                ->whereBetween('created_at', [$startTime, $endTime])
                ->get();
        } else {
            $liveTrades = collect();
            $liveTradesData = collect();
        }



        // dd($liveTrades);
        $liveBuy = [];
        $liveSell = [];
        foreach ($data as $index => &$candle) {

            // Convert candle timestamp to Unix timestamp
            $candleTime = strtotime($candle['timestamp']);
            // Define the interval window (+- 5 minutes)
            $startWindow = $candleTime - (5 * 60);
            $endWindow = $candleTime + (5 * 60);

            // Iterate through the live trades to find matching entries
            foreach ($liveTrades as $key => $trade) {
                $tradeTime = strtotime($trade->created_at);
                if ($tradeTime >= $startWindow && $tradeTime <= $endWindow) {

                    if ($trade->type === 'open') {
                        $liveBuy[] = $candle['binance_timestamp'];
                        $liveTrades->forget($key);
                    } elseif ($trade->type === 'close') {
                        $liveSell[] = $candle['binance_timestamp'];
                        $liveTrades->forget($key);
                    }
                }
            }
        }




        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true, $data[0]['binance_timestamp'], 1000);



        $tradeMarkers = [];


        foreach ($trades as $index => $trade) {

            $tradeMarkers[] = [
                'timestamp_pst' => $trade->buyingCandle->timestamp_pst,
                'color' => 'green',
                'text' => 'Open ' . $index + 1,
                'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
            ];

            $tradeMarkers[] = [
                'timestamp_pst' => $trade->sellingCandle->timestamp_pst,
                'color' => 'red',
                'text' => 'Close ' . $index + 1,
                'position' => $trade->position === 'SHORT' ? 'belowBar' : 'aboveBar'
            ];



            $buyingCandle = $trade->buyingCandle;
            if (isset($buyingCandle->lowPivots)) {
                foreach ($buyingCandle->lowPivots as $lpIndex => $lp) {
                    $lpCount =  $lpIndex + 1;
                    $tradeMarkers[] = [
                        'timestamp_pst' => $lp,
                        'color' => 'orange',
                        'text' => 'LP ' . $lpCount,
                        'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
                    ];
                }
            }
             if (isset($buyingCandle->highPivots)) {
                foreach ($buyingCandle->highPivots as $hpIndex => $hp) {
                    $hpCount =  $hpIndex + 1;
                    $tradeMarkers[] = [
                        'timestamp_pst' => $hp,
                        'color' => 'blue',
                        'text' => 'HP ' . $hpCount,
                        'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
                    ];
                }
            }
        }

        return view('CoinReports.coin-report-details', [
            'pageSlug' => 'Report Details',
            'symbol' => $symbol,
            'formula' => $formula,
            'interval' => $interval,
            'trades' => $trades,
            'stopLoss' => $stopLoss,
            'market' => $market,
            'liveBuy' => $liveBuy,
            'liveSell' => $liveSell,
            'data' => $data,
            'volumeSignals' => $volumeSignals,
            'liveTradesData' => $liveTradesData,
            'tradeMarkers' => $tradeMarkers,


        ]);
    }

    public function showTrends($market, Request $request)
    {



        // $formulas = [
        //     'Analysis - Current - Base - Saturday, July 19, 2025 11:36 PM',
        //     'Analysis - Bullish - Base - Saturday, July 19, 2025 11:37 PM',
        //     'Analysis - Slight Bearish - Base - Saturday, July 19, 2025 11:37 PM',
        //     // 'Analysis - Slight Bullish - Base - Saturday, July 19, 2025 11:38 PM',
        //     // 'Analysis - Flat - Base - Saturday, July 19, 2025 11:38 PM',
        //     // 'Analysis - Mixed - Base - Saturday, July 19, 2025 11:38 PM',
        // ];


        // $bestPerformingSymbolsSet = [];

        // $tableName = 'coin_reports';

        // foreach ($formulas as $formula) {


        //     $baseQuery = DB::table($tableName)->where('market', $market);

        //     if ($formula) {
        //         $baseQuery->where('formula', $formula);
        //     }

        //     // To filter only completed trades
        //     $baseQuery->whereNotNull('sellingCandle');

        //     // Clone the base query for reuse
        //     $tradeDataQuery = clone $baseQuery;
        //     $thresholdPercent = 90;
        //     // Get aggregated trade data
        //     $tradeData = $tradeDataQuery->select(
        //         'symbol',
        //         'formula',
        //         'position',
        //         'interval',
        //         DB::raw('COUNT(*) as total_entries'),
        //         DB::raw('SUM(profit) as total_profit'),
        //         DB::raw('SUM(CASE WHEN profit < 0 THEN 1 ELSE 0 END) as number_of_sl'),
        //         DB::raw('AVG(profit) as average_profit'),
        //         DB::raw('AVG(duration) as average_duration'),
        //         DB::raw('SUM(duration) as total_duration'),
        //         DB::raw('MAX(profit) as max_profit'),
        //         DB::raw('MIN(profit) as min_profit'),
        //         DB::raw('MAX(lowestPricePercentage) as max_lowestPrice'),
        //         DB::raw('MIN(lowestPricePercentage) as min_lowestPrice'),
        //         DB::raw('MAX(created_at) as last_updated')
        //     )

        //         ->groupBy('symbol', 'position', 'formula', 'interval')
        //         ->orderBy('total_entries', 'DESC')
        //         ->orderBy('last_updated', 'DESC')
        //         ->get();





        //     foreach ($tradeData as $symbolTrade) {


        //         $accuracy = (($symbolTrade->total_entries - $symbolTrade->number_of_sl) / ($symbolTrade->total_entries)) * 100;

        //         if ($accuracy >= $thresholdPercent) {

        //             if (isset($bestPerformingSymbolsSet[$symbolTrade->symbol])) {
        //                 $bestPerformingSymbolsSet[$symbolTrade->symbol] = $bestPerformingSymbolsSet[$symbolTrade->symbol] + 1;
        //             } else {
        //                 $bestPerformingSymbolsSet[$symbolTrade->symbol] = 1;
        //             }
        //         }
        //     }
        // }




        // arsort($bestPerformingSymbolsSet);


        // dd($bestPerformingSymbolsSet);























        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '15m');
        $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, 1000, null, 'FUTURE');

        $openingMarkers = [];
        $lines = [];
        $equations = [];
        $pivotLowZone = null;




        $openDetails = null;
        $tp = request('tp', '0.5');

        $sl = request('sl', '1');

        $lineObj = [
            'x1' => null, // timestamp for first point
            'y1' => null,         // price for first point
            'x2' => null, // timestamp for second point
            'y2' => null,         // price for second point
            'color' => '#ff0000',  // red color
            'thickness' => 2,      // line thickness
            'title' => 'Support Line'
        ];

        $tlineHigh = null;
        $waitingCandles = 0;
        $lastLowPivot = null;
        $lowPivots = [];
        $highPivots = [];

        foreach ($data as $index => &$candle) {

            if ($index < 200 || $index > count($data) - 100) {
                continue;
            }
            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }


            $pivot = CommonHelpers::checkPivot($data, $index - 6, 6);






            if ($pivot === 'low_pivot') {

                $openingMarkers[] = [
                    'timestamp_pst' => $data[$index - 6]['timestamp_pst'],
                    'color' => 'blue',
                    'text' => 'Low',
                    'position' => 'belowBar'
                ];
                $lowPivots[] = $index - 6;
            }

            if ($pivot === 'high_pivot') {

                $openingMarkers[] = [
                    'timestamp_pst' => $data[$index - 6]['timestamp_pst'],
                    'color' => 'orange',
                    'text' => 'High',
                    'position' => 'aboveBar'
                ];
                $highPivots[] = $index - 6;
            }


            $lastPivotIndex = count($lowPivots) - 1;

            if (

                $pivot === 'low_pivot'
                && count($lowPivots) > 3
                && $data[$lowPivots[$lastPivotIndex]]['low'] > $data[$lowPivots[$lastPivotIndex - 1]]['low']
                && $data[$lowPivots[$lastPivotIndex - 1]]['low'] < $data[$lowPivots[$lastPivotIndex - 2]]['low']
                && $data[$lowPivots[$lastPivotIndex - 2]]['low'] < $data[$lowPivots[$lastPivotIndex - 3]]['low']

            ) {



                $firstPivotIndex = count($lowPivots) - 3;
                $firstPivot = $lowPivots[$firstPivotIndex];
                $lastPivot = $lowPivots[$lastPivotIndex];
                $highPivots = [];
                for ($i = $firstPivot; $i <= $lastPivot; $i++) {
                    $minorPivot = CommonHelpers::checkPivot($data, $i, 6);
                    if ($minorPivot === 'high_pivot') {
                        $highPivots[] = $i;
                        $openingMarkers[] = [
                            'timestamp_pst' => $data[$i]['timestamp_pst'],
                            'color' => 'red',
                            'text' => 'Minor High',
                            'position' => 'aboveBar'
                        ];
                    }
                }






                $openingMarkers[] = [
                    'timestamp_pst' => $candle['timestamp_pst'],
                    'color' => 'green',
                    'text' => 'Long',
                    'position' => 'belowBar'
                ];


                $initialTp = $candle['close'] * (1 + 0.5 / 100);
                $initialSl = $candle['close'] * (1 - 1 / 100);


                $lines[] = [
                    'x1' => $data[$index - 3]['timestamp_pst'], // timestamp for first point
                    'y1' => $initialTp,         // price for first point
                    'x2' => $data[$index + 3]['timestamp_pst'], // timestamp for second point
                    'y2' => $initialTp,         // price for second point
                    'color' => 'green',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Expected Tp'
                ];

                $lines[] = [
                    'x1' => $data[$index - 3]['timestamp_pst'], // timestamp for first point
                    'y1' => $initialSl,         // price for first point
                    'x2' => $data[$index + 3]['timestamp_pst'], // timestamp for second point
                    'y2' => $initialSl,         // price for second point
                    'color' => 'red',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Expected Sl'
                ];
            }










            $lastPivotIndexHigh = count($highPivots) - 1;

            if (

                $pivot === 'high_pivot'
                && count($highPivots) > 3
                && $data[$highPivots[$lastPivotIndexHigh]]['high'] < $data[$highPivots[$lastPivotIndexHigh - 1]]['high']
                && $data[$highPivots[$lastPivotIndexHigh - 1]]['high'] > $data[$highPivots[$lastPivotIndexHigh - 2]]['high']
                && $data[$highPivots[$lastPivotIndexHigh - 2]]['high'] > $data[$highPivots[$lastPivotIndexHigh - 3]]['high']

            ) {



                $firstPivotIndex = count($highPivots) - 3;
                $firstPivot = $highPivots[$firstPivotIndex];
                $lastPivot = $highPivots[$lastPivotIndexHigh];
                $lowPivots = [];
                for ($i = $firstPivot; $i <= $lastPivot; $i++) {
                    $minorPivot = CommonHelpers::checkPivot($data, $i, 6);
                    if ($minorPivot === 'low_pivot') {
                        $lowPivots[] = $i;
                        $openingMarkers[] = [
                            'timestamp_pst' => $data[$i]['timestamp_pst'],
                            'color' => 'pink',
                            'text' => 'Minor Low',
                            'position' => 'belowBar'
                        ];
                    }
                }

                if (count($lowPivots) >= 2) {

                    $lastLowPivot = count($lowPivots) - 1;
                    $firstLowPivot = count($lowPivots) - 2;
                    if (
                        $data[$lowPivots[$firstLowPivot]]['low'] > $data[$lowPivots[$lastLowPivot]]['low']
                    ) {
                        continue;
                    }
                }


                if (count($lowPivots) == 0) {
                    continue;
                }



                $openingMarkers[] = [
                    'timestamp_pst' => $candle['timestamp_pst'],
                    'color' => 'white',
                    'text' => 'Short',
                    'position' => 'aboveBar'
                ];


                $initialTp = $candle['close'] * (1 - 0.5 / 100);
                $initialSl = $candle['close'] * (1 + 1 / 100);


                $lines[] = [
                    'x1' => $data[$index - 3]['timestamp_pst'], // timestamp for first point
                    'y1' => $initialTp,         // price for first point
                    'x2' => $data[$index + 3]['timestamp_pst'], // timestamp for second point
                    'y2' => $initialTp,         // price for second point
                    'color' => 'green',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Expected Tp'
                ];

                $lines[] = [
                    'x1' => $data[$index - 3]['timestamp_pst'], // timestamp for first point
                    'y1' => $initialSl,         // price for first point
                    'x2' => $data[$index + 3]['timestamp_pst'], // timestamp for second point
                    'y2' => $initialSl,         // price for second point
                    'color' => 'red',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Expected Sl'
                ];
            }
        }




        return view('MarketTrends.index', ['data' => $data, 'pageSlug' => 'MarketTrends' . $market, 'openingMarkers' => $openingMarkers, 'lines' => $lines, 'equations' => $equations, 'symbol' => $symbol, 'interval' => $interval]);
    }
















    public function analyzePivots($pivots)
    {
        // Separate highs and lows
        $highs = [];
        $lows = [];

        foreach ($pivots as $pivot) {
            if ($pivot['type'] === 'high_pivot') {
                $highs[] = $pivot;
            } else {
                $lows[] = $pivot;
            }
        }

        // Sort by index to ensure chronological order
        usort($highs, function ($a, $b) {
            return $a['index'] - $b['index'];
        });

        usort($lows, function ($a, $b) {
            return $a['index'] - $b['index'];
        });

        return [
            'highs' => $highs,
            'lows' => $lows,
            'high_trendlines' => $this->calculateTrendlines($highs),
            'low_trendlines' => $this->calculateTrendlines($lows),
            'zones' => $this->findConcentrationZones($pivots)
        ];
    }

    private function calculateTrendlines($pivots)
    {
        $trendlines = [];

        if (count($pivots) < 2) {
            return $trendlines;
        }

        // Find major trendlines by connecting significant pivots
        for ($i = 0; $i < count($pivots) - 1; $i++) {
            for ($j = $i + 1; $j < count($pivots); $j++) {
                $start = $pivots[$i];
                $end = $pivots[$j];

                // Calculate trendline strength (more pivots touching = stronger)
                $strength = $this->calculateTrendlineStrength($start, $end, $pivots);

                if ($strength >= 2) { // At least 2 touching points
                    $trendlines[] = [
                        'start' => [
                            'index' => $start['index'],
                            'price' => $start['price']
                        ],
                        'end' => [
                            'index' => $end['index'],
                            'price' => $end['price']
                        ],
                        'strength' => $strength,
                        'slope' => ($end['price'] - $start['price']) / ($end['index'] - $start['index'])
                    ];
                }
            }
        }

        // Sort by strength (strongest first)
        usort($trendlines, function ($a, $b) {
            return $b['strength'] - $a['strength'];
        });

        return array_slice($trendlines, 0, 5); // Return top 5 trendlines
    }

    private function calculateTrendlineStrength($start, $end, $pivots)
    {
        $strength = 0;
        $tolerance = 0.001; // 0.1% tolerance for price touching trendline

        foreach ($pivots as $pivot) {
            if ($pivot['index'] >= $start['index'] && $pivot['index'] <= $end['index']) {
                // Calculate expected price at this index on the trendline
                $expectedPrice = $start['price'] +
                    (($pivot['index'] - $start['index']) / ($end['index'] - $start['index'])) *
                    ($end['price'] - $start['price']);

                // Check if pivot price is close to trendline
                if (abs($pivot['price'] - $expectedPrice) / $expectedPrice < $tolerance) {
                    $strength++;
                }
            }
        }

        return $strength;
    }

    private function findConcentrationZones($pivots)
    {
        $zones = [];
        $priceRanges = [];

        // Group pivots by price ranges (bins)
        foreach ($pivots as $pivot) {
            $priceLevel = round($pivot['price'] / 100) * 100; // 100-point bins

            if (!isset($priceRanges[$priceLevel])) {
                $priceRanges[$priceLevel] = [];
            }

            $priceRanges[$priceLevel][] = $pivot;
        }

        // Find zones with most pivots
        foreach ($priceRanges as $level => $pivots) {
            if (count($pivots) >= 3) { // At least 3 pivots in zone
                $zones[] = [
                    'price_level' => $level,
                    'pivot_count' => count($pivots),
                    'min_price' => min(array_column($pivots, 'price')),
                    'max_price' => max(array_column($pivots, 'price')),
                    'pivots' => $pivots
                ];
            }
        }

        // Sort by pivot count (highest concentration first)
        usort($zones, function ($a, $b) {
            return $b['pivot_count'] - $a['pivot_count'];
        });

        return $zones;
    }

    public function formatForChart($analysis)
    {
        $chartData = [
            'high_trendlines' => [],
            'low_trendlines' => [],
            'zones' => []
        ];

        // Format high trendlines for charting
        foreach ($analysis['high_trendlines'] as $line) {
            $chartData['high_trendlines'][] = [
                'x1' => $line['start']['index'],
                'y1' => $line['start']['price'],
                'x2' => $line['end']['index'],
                'y2' => $line['end']['price'],
                'strength' => $line['strength']
            ];
        }

        // Format low trendlines for charting
        foreach ($analysis['low_trendlines'] as $line) {
            $chartData['low_trendlines'][] = [
                'x1' => $line['start']['index'],
                'y1' => $line['start']['price'],
                'x2' => $line['end']['index'],
                'y2' => $line['end']['price'],
                'strength' => $line['strength']
            ];
        }

        // Format zones
        $chartData['zones'] = $analysis['zones'];

        return $chartData;
    }
















    public function getAvailableBalance(Request $request)
    {
        return BinanceApiService::fetchAvailableQuantity($request->symbol, Auth::user()->id, $request->market);
    }
    public function showAverages($market, Request $request)
    {
        $averages = DB::table('ideal_buying_candles')
            ->select(
                'symbol',
                'interval',
                DB::raw('AVG(volume) as avg_volume'),
                DB::raw('AVG(ma7) as avg_ma7'),
                DB::raw('AVG(ma14) as avg_ma14'),
                DB::raw('AVG(ma25) as avg_ma25'),
                DB::raw('AVG(ma99) as avg_ma99'),
                DB::raw('AVG(rsi6) as avg_rsi6'),
                DB::raw('AVG(per) as avg_per'),
                DB::raw('AVG(dif) as avg_dif'),
                DB::raw('AVG(dea) as avg_dea'),
                DB::raw('AVG(histogram) as avg_histogram'),
                DB::raw('AVG(sar) as avg_sar'),
                DB::raw('AVG(obv) as avg_obv'),
                DB::raw('AVG(stoch_rsi) as avg_stoch_rsi'),
                DB::raw('AVG(stoch_k) as avg_stoch_k'),
                DB::raw('AVG(stoch_d) as avg_stoch_d'),
                DB::raw('AVG(previousObvHigh) as avg_previousObvHigh'),
                DB::raw('AVG(wr) as avg_wr'),
                DB::raw('AVG(K) as avg_K'),
                DB::raw('AVG(D) as avg_D'),
                DB::raw('AVG(J) as avg_J')
            )
            ->where('market', $market)
            ->where('interval', $request->interval)
            ->groupBy('symbol', 'interval') // Include 'market' in the group by clause
            ->get();




        return view('IdealIndicators.index', ['averages' => $averages, 'pageSlug' => 'averageCandlesticks' . $market]);
    }
    public function liveTradeResults($market, Request $request)
    {

        if ($market === 'SPOT') {







            $pageSlug = 'liveTradeResults' . $market;
            $symbols = DB::table('live_trades_spot_results')
                ->select('symbol')
                ->distinct()
                ->where('trade_acc', Auth::user()->id)
                ->get();

            $formulas = DB::table('live_trades_spot_results')
                ->select('formula')
                ->distinct()
                ->where('trade_acc', Auth::user()->id)
                ->get();


            $orders = DB::table('live_trades_spot_results')
                ->where('trade_acc', Auth::user()->id)
                ->where('type', 'open');
            if ($request->filled('start_date'))
                $orders = $orders->where(
                    'created_at',
                    '>=',
                    Carbon::parse($request->start_date)->format('Y-m-d H:i:s')
                );
            if ($request->filled('end_date'))
                $orders = $orders->where(
                    'created_at',
                    '<=',
                    Carbon::parse($request->end_date)->format('Y-m-d H:i:s')
                );
            if ($request->filled('symbol')) {
                $orders = $orders->where('symbol', $request->symbol);
            }
            if ($request->filled('formula'))
                $orders = $orders->where('formula', 'LIKE', $request->input('formula'));

            $orders = $orders->orderByRaw("trade_status = 'open' DESC")
                ->orderBy('created_at', 'desc')
                ->get();


            $tradeStatistics = [
                'total_orders' => 0,
                'total_short' => 0,
                'total_long' => 0,
                'total_profit' => 0,
                'total_loss' => 0,
                'net_total' => 0,
                'realizedPnl' => 0,

            ];

            foreach ($orders as $order) {
                $tradeStatistics['total_orders'] += 1;
                $tradeStatistics['net_total'] += $order->currentProfit;
                $tradeStatistics['realizedPnl'] += $order->realizedPnl;

                if ($order->position === 'LONG')
                    $tradeStatistics['total_long'] += 1;
                if ($order->position === 'SHORT')
                    $tradeStatistics['total_short'] += 1;

                if ($order->currentProfit >= 0)
                    $tradeStatistics['total_profit'] += $order->currentProfit;

                if ($order->currentProfit < 0)
                    $tradeStatistics['total_loss'] += abs($order->currentProfit);
            }
            // dd($orders);
            return view('live-trades.results-spot', compact('orders', 'tradeStatistics', 'pageSlug', 'symbols', 'formulas'));
        } else if ($market === 'FUTURE') {


            $pageSlug = 'liveTradeResults' . $market;
            $symbols = DB::table('live_trades_future_results')
                ->select('symbol')
                ->distinct()
                ->where('trade_acc', Auth::user()->id)
                ->get();

            $formulas = DB::table('live_trades_future_results')
                ->select('formula')
                ->distinct()
                ->where('trade_acc', Auth::user()->id)
                ->get();


            $orders = DB::table('live_trades_future_results')
                ->where('trade_acc', Auth::user()->id)
                ->where('type', 'open');
            if ($request->filled('start_date'))
                $orders = $orders->where(
                    'created_at',
                    '>=',
                    Carbon::parse($request->start_date)->format('Y-m-d H:i:s')
                );
            if ($request->filled('end_date'))
                $orders = $orders->where(
                    'created_at',
                    '<=',
                    Carbon::parse($request->end_date)->format('Y-m-d H:i:s')
                );
            if ($request->filled('symbol')) {
                $orders = $orders->where('symbol', $request->symbol);
            }
            if ($request->filled('formula'))
                $orders = $orders->where('formula', 'LIKE', $request->input('formula'));

            $orders = $orders->orderByRaw("trade_status = 'open' DESC")
                ->orderBy('created_at', 'desc')
                ->get();


            $tradeStatistics = [
                'total_orders' => 0,
                'total_short' => 0,
                'total_long' => 0,
                'total_profit' => 0,
                'total_loss' => 0,
                'net_total' => 0,
                'realizedPnl' => 0,

            ];

            foreach ($orders as $order) {
                $tradeStatistics['total_orders'] += 1;
                $tradeStatistics['net_total'] += $order->currentProfit;
                $tradeStatistics['realizedPnl'] += $order->realizedPnl;

                if ($order->position === 'LONG')
                    $tradeStatistics['total_long'] += 1;
                if ($order->position === 'SHORT')
                    $tradeStatistics['total_short'] += 1;

                if ($order->currentProfit >= 0)
                    $tradeStatistics['total_profit'] += $order->currentProfit;

                if ($order->currentProfit < 0)
                    $tradeStatistics['total_loss'] += abs($order->currentProfit);
            }
            // dd($orders);
            return view('live-trades.results-future', compact('orders', 'tradeStatistics', 'pageSlug', 'symbols', 'formulas'));
        }
    }


    public function liveTradeCoins($market, Request $request)
    {
        if ($market === 'SPOT') {
            // $pageSlug = 'liveTradeResults' . $market;
            // $orders = DB::table('orders')->where('market', $market)->where('trade_acc', Auth::user()->id)
            //     ->where('side', 'BUY');

            // if ($request->filled('start_date'))
            //     $orders = $orders->where('created_at', '>=', Carbon::parse($_GET['start_date'])->format('Y-m-d H:i:s'));
            // if ($request->filled('end_date'))
            //     $orders = $orders->where('created_at', '<=', Carbon::parse($_GET['end_date'])->format('Y-m-d H:i:s'));
            // if ($request->filled('symbol'))
            //     $orders = $orders->where('symbol', $_GET['symbol']);
            // $orders = $orders->orderBy('created_at', 'desc')->get();
            // // dd($orders);
            // return view('live-trades.results', compact('orders', 'pageSlug'));
        } else if ($market === 'FUTURE') {
            $pageSlug = 'coins' . $market;
            $coins = DB::table('trade_handler')->where('market', "FUTURE")
                ->distinct('symbol')

                ->where('tradeAccount', Auth::user()->id)
                ->get();

            // dd($coins);
            return view('live-trades.coins', compact('coins', 'pageSlug'));
        }
    }
    public function liveTradeDetails($interval, $market, $symbol, Request $request)
    {

        $pageSlug = 'liveTradeDetails';
        $order_buy = DB::table('orders')->where('side', 'BUY')->where('symbol', $symbol)->where('interval', $interval)->get();
        $order_sell = DB::table('orders')->where('side', 'SELL')->where('symbol', $symbol)->where('interval', $interval)->get();
        $candlestickData = MarketTrendService::getSymbolHistoricalTrendsSet2($symbol, $interval, $market, $request->timestamp);

        foreach ($candlestickData as $index => &$candle) {

            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
        }


        // dd($orders);
        return view('live-trades.trade-details', compact('pageSlug', 'symbol', 'interval', 'market', 'order_sell', 'order_buy', 'candlestickData'));
    }

    public function closeFutureTrade($orderId)
    {
        BinanceApiService::closeMarketPositionLiveTrader($orderId);
        return redirect()->back()->withSuccess('Trade Closed Successfully');
    }
    public function closeSpotTrade($orderId)
    {
        BinanceApiService::placeSellOrderSpot($orderId);
        return redirect()->back()->withSuccess('Trade Closed Successfully');
    }

    // Api Routes for external db replacement 
    public function getAllSymbols()
    {
        return  response()->json([
            'data' => DB::table('coins')->where('market', 'FUTURE')->where('status', 'T')->get()
        ]);
    }

    public function getSafeModeStatus($symbol, $position)
    {
        return  response()->json([
            'data' => CommonHelpers::getSafeModeStatus($symbol, $position),
        ]);
    }

    public function getSafeModeAccuracy($position, $formula, $tagName = null)
    {

        $timestampMillis = round(microtime(true) * 1000);
        $progressionDetails = ReportServiceSafeModeMacdSwing::getProgressionDetails($formula, $position, $timestampMillis, $tagName);
        $stats = ReportServiceSafeModeMacdSwing::parseStats($progressionDetails, $timestampMillis, 6);
        $lastUpdateTime = DB::table('coin_reports_safe_mode')->where('formula', $formula)->orderBy('created_at', 'DESC')->first();

        return  response()->json([
            'data' => [
                'accuracy' => $stats['accuracy'],
                'profits' => $stats['profitable'],
                'losses' => $stats['losses'],
                'total' => $stats['total'],
                'lastUpdateTime' => $lastUpdateTime ? Carbon::parse($lastUpdateTime->created_at, 'UTC')
                    ->setTimezone('Asia/Karachi')
                    ->format('d M Y h:i A') : null,
            ],
        ]);
    }
}
