<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\IdealTradeService;
use App\Services\InternalTrader\ReportService;
use App\Services\InternalTrader\ReportServiceSafeMode;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use App\Services\ReportService\LongReportService;
use App\Services\TradingGapAnalyzer;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
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
            return redirect()->back()->withSuccess('Current Report Deleted Successfully!');
        } else if (request('incomplete_only')) {

            $formulas =   DB::table('formula_details')->where('progress', '!=', 100)->pluck('formula');
            DB::table('coin_reports')->whereIn('formula', $formulas)->delete();
            DB::table('formula_details')->whereIn('formula', $formulas)->delete();
            return redirect()->back()->withSuccess(count($formulas) . " Reports Deleted Successfully!");
        } else if (request('delete_all')) {
            $count =   DB::table('formula_details')->get()->count();
            DB::table('coin_reports')->truncate();
            DB::table('formula_details')->truncate();
            return redirect()->back()->withSuccess($count . " Reports Deleted Successfully!");
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

        // Return early with default values if no formula provided
        if (!$request->has('formula')) {
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
            ]);
        }

        // Build base query with common filters
        $baseQuery = DB::table('coin_reports')->where('market', $market);

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
        $formulaDetails = DB::table('formula_details')->where('formula', $formula)->first();
        $formulaConfig = json_decode($formulaDetails->report_config, true);



        $trendReferenceSymbol = ($formulaConfig && $formulaConfig['trendReferenceSymbol']) ? $formulaConfig['trendReferenceSymbol'] : 'BTCUSDT';

        // $trendReferenceInterval = ($formulaConfig && $formulaConfig['trendReferenceInterval']) ? $formulaConfig['trendReferenceInterval'] : '1h';
        $trendReferenceInterval = ($formulaConfig && $formulaConfig['trendReferenceInterval']) ? $formulaConfig['trendReferenceInterval'] : '1h';

        $date = new DateTime($formulaDetails->created_at, new DateTimeZone('Asia/Karachi'));
        $date->setTimezone(new DateTimeZone('UTC')); // convert to UTC
        $createdTimestampMs = $date->getTimestamp() * 1000; // get UTC timestamp in milliseconds

        $startUnix = ($formulaConfig && $formulaConfig['startUnix'])
            ? $formulaConfig['startUnix']
            : ($createdTimestampMs - (CommonHelpers::$binanceIntervals[$interval] * 60 * 1000 * 1000));

        $endUnix = ($formulaConfig && $formulaConfig['endUnix'])
            ? $formulaConfig['endUnix']
            : $createdTimestampMs;

        $intervalMs = CommonHelpers::$binanceIntervals[$trendReferenceInterval] * 60 * 1000; // Interval in ms


        $candleCount = intval(($endUnix - $startUnix) / $intervalMs);


        $dataTrendReference = BinanceApiService::getCandleStickData($trendReferenceSymbol, $trendReferenceInterval, $candleCount, $startUnix, 'FUTURE');








        // Trend reference settings for actual interval
        $trendReferenceSymbolActual = count($tradeData) ? $tradeData[0]->symbol : 'BTCUSDT';
        $dataTrendReferenceActual = BinanceApiService::getCandleStickData($trendReferenceSymbolActual, $interval, 1000, $startUnix, 'FUTURE');




        // Initialize statistics counters
        $rsiLimit = 65;
        $wrLimit = -10;
        $accuracyThreshold = 90;
        $accuracyThresholdLow = 80;

        $rsiAbove40Profitable = $rsiAbove40Loss = $rsiAbove40Total = 0;
        $rsiBelow40Profitable = $rsiBelow40Loss = $rsiBelow40Total = 0;

        $tradesBelowTP = 0;
        $tpLimit = 0.5;

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



        $startingTimestamp = $formulaConfig['startUnix'];
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
        $timelineData = array_map(function ($trade) use ($stopLoss) {
            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);
            $trade['sellingCandle'] = json_decode($trade['sellingCandle'], true);

            $color = '';
            if ($trade['profit'] < 0) {
                $color = 'yellow';
            }

            return [
                'symbol' => $trade['symbol'] . '( ' . $trade['position'] . ' )',
                'startTime' => $trade['buyingCandle']['timestampReadable'],
                'endTime' => $trade['sellingCandle']['timestampReadable'],
                'color' => $color ? $color : ($trade['position'] === 'SHORT' ? 'red' : 'green'),
                'id' => $trade['id'],
                'buyingCandle' => $trade['buyingCandle'],
            ];
        }, $tradeArr);




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


        $openTradesQuery =  DB::table('coin_reports')->where('market', $market);

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

        $progressionDetailsLONG = ReportService::getProgressionDetails($formula, 'LONG', $endUnix);
        $progressionDetailsSHORT = ReportService::getProgressionDetails($formula, 'SHORT', $endUnix);

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


            $candle['accuracy_long'] = ReportService::parseAccuracy($progressionDetailsLONG, $candle['binance_timestamp']);
            $candle['accuracy_short'] = ReportService::parseAccuracy($progressionDetailsSHORT, $candle['binance_timestamp']);
        }



        $analyzer = new TradingGapAnalyzer();
        $result = $analyzer->findMaxTradingGap($timeWiseTradeCountProfitable, 0, 0);

        // dd($result);

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


        $date = new DateTime($formulaDetails->created_at, new DateTimeZone('Asia/Karachi'));
        $date->setTimezone(new DateTimeZone('UTC')); // convert to UTC
        $createdTimestampMs = $date->getTimestamp() * 1000; // get UTC timestamp in milliseconds

        $startUnix = ($formulaConfig && $formulaConfig['startUnix'])
            ? $formulaConfig['startUnix']
            : ($createdTimestampMs - (CommonHelpers::$binanceIntervals[$interval] * 60 * 1000 * 1000));

        $endUnix = ($formulaConfig && $formulaConfig['endUnix'])
            ? $formulaConfig['endUnix']
            : $createdTimestampMs;

        // Fetching Base Candle Data
        // $startTime = $trades->first()->buyingCandle->binance_timestamp - (CommonHelpers::$binanceIntervals[$interval] * 100 * 60 * 1000);

        $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000, $startUnix, $market);

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
        ]);
    }

    public function showTrends($market, Request $request)
    {
        $trends = DB::table('market_trends')->where('market', $market)->where('interval', $request->interval)->get();
        $historicalTrends = MarketTrendService::getVolumesGraph($request->symbol);








        return view('MarketTrends.index', ['trends' => $trends, 'pageSlug' => 'MarketTrends' . $market, 'historicalTrends' => $historicalTrends['data'], 'volumeSignals' => [], 'totalProfit' => 0]);
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

    public function getSafeModeAccuracy($position)
    {

        $formula = '';
        $timestampMillis = round(microtime(true) * 1000);
        $formula = 'Safe Mode Base Report';
        $progressionDetails = ReportServiceSafeMode::getProgressionDetails($formula, $position, $timestampMillis);
        $stats = ReportServiceSafeMode::parseStats($progressionDetails, $timestampMillis, 6);
        return  response()->json([
            'data' => [
                'accuracy' => $stats['accuracy'],
                'profits' => $stats['profitable'],
                'losses' => $stats['losses'],
                'total' => $stats['total'],
            ],
        ]);
    }
}
