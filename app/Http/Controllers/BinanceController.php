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
use App\Services\OpeningConditionServiceLive;
use App\Services\OrderBlockService;
use App\Services\OrderBookStrategy;
use App\Services\ReportService\LongReportService;
use App\Services\SmartMoneyConceptsService;
use App\Services\SupportResistanceAnalyzer;
use App\Services\TradingGapAnalyzer;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\map;

class BinanceController extends Controller
{

    private SmartMoneyConceptsService $smcService;

    public function __construct()
    {
        // Initialize SMC service with custom configuration
        $this->smcService = new SmartMoneyConceptsService([
            'swing_length' => 5,
            'max_order_blocks' => 5,
            'max_fvgs' => 5,
            'swing_limit' => 100,
            'ob_mitigation_method' => 'close',
            'fvg_mitigation_method' => 'close',
            'fvg_threshold' => 0.1,
            'build_sweeps' => true,
            'overlap_filter' => true,
            'atr_length' => 200,
            'ob_mode' => 'length',
            'ob_length_multiplier' => 1.0
        ]);
    }

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


        $maxSL = 0;
        $minSL = PHP_FLOAT_MAX;
        $sumSL = 0;

        $maxLP = 0;
        $minLP = PHP_FLOAT_MAX;
        $sumLP = 0;
        $tradesAbove5perSL = 0;
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


            if (isset($buyingCandle['slPer'])) {
                if ($maxSL < $buyingCandle['slPer']) {
                    $maxSL = $buyingCandle['slPer'];
                }
                if ($minSL > $buyingCandle['slPer']) {
                    $minSL = $buyingCandle['slPer'];
                }

                if ($buyingCandle['slPer'] <= 5) {
                    $tradesAbove5perSL++;
                }

                $sumSL += $buyingCandle['slPer'];
            }
            if (isset($trade['lowestPricePercentage'])) {
                if ($maxLP < $trade['lowestPricePercentage']) {
                    $maxLP = $trade['lowestPricePercentage'];
                }
                if ($minLP > $trade['lowestPricePercentage']) {
                    $minLP = $trade['lowestPricePercentage'];
                }


                $sumLP += $trade['lowestPricePercentage'];
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
        }




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

        $progressionDetailsLONG =  CommonHelpers::getProgressionDetails($formula, 'LONG', $endUnix);
        $progressionDetailsSHORT = CommonHelpers::getProgressionDetails($formula, 'SHORT', $endUnix);



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


            $candle['accuracy_long'] =  CommonHelpers::parseAccuracy($progressionDetailsLONG, $candle['binance_timestamp'], 6);
            $candle['accuracy_short'] =   CommonHelpers::parseAccuracy($progressionDetailsSHORT, $candle['binance_timestamp'], 6);


            $profitLong = CommonHelpers::parseProfit($progressionDetailsLONG, $candle['binance_timestamp']);
            $profitShort = CommonHelpers::parseProfit($progressionDetailsSHORT, $candle['binance_timestamp']);

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

            'maxSL' => count($tradeArr) ? $maxSL : 0,
            'minSL' => count($tradeArr) ? $minSL : 0,
            'avgSL' => count($tradeArr) ? $sumSL / count($tradeArr) : 0,

            'maxLP' => count($tradeArr) ? $maxLP : 0,
            'minLP' => count($tradeArr) ? $minLP : 0,
            'avgLP' => count($tradeArr) ? $sumLP / count($tradeArr) : 0,

            'tradesAbove5perSL' => $tradesAbove5perSL,

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



        $userId = Auth::user() ? Auth::user()->id : 2;

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

        $lines = [];
        $otherMarkers = [];
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


            // $candle['rsi6'] = $candle['atr14'];
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
                'type' => $trade->position,
                'startTimestamp' => $trade->buyingCandle->timestamp_pst, // Unix milliseconds
                'endTimestamp' => $trade->sellingCandle->timestamp_pst,
                'entryPrice' => $trade->buyingCandle->close,
                'tp' => isset($trade->buyingCandle->dynamicTP) ? $trade->buyingCandle->dynamicTP :  $trade->buyingCandle->close,
                'sl' => isset($trade->buyingCandle->dynamicSL) ? $trade->buyingCandle->dynamicSL :  $trade->buyingCandle->close,
                'tpColor' => 'rgba(0, 255, 0, 0.3)',
                'slColor' => 'rgba(255, 0, 0, 0.3)',
                'profit' => $trade->profit,
            ];







            $buyingCandle = $trade->buyingCandle;
            $sellingCandle = $trade->sellingCandle;
            // if (isset($buyingCandle->lowPivots)) {
            //     foreach ($buyingCandle->lowPivots as $lpIndex => $lp) {
            //         $lpCount =  $lpIndex + 1;
            //         $tradeMarkers[] = [
            //             'timestamp_pst' => $lp,
            //             'color' => 'orange',
            //             'text' => 'LP ' . $lpCount,
            //             'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
            //         ];
            //     }
            // }
            // if (isset($buyingCandle->highPivots)) {
            //     foreach ($buyingCandle->highPivots as $hpIndex => $hp) {
            //         $hpCount =  $hpIndex + 1;
            //         $tradeMarkers[] = [
            //             'timestamp_pst' => $hp,
            //             'color' => 'blue',
            //             'text' => 'HP ' . $hpCount,
            //             'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
            //         ];
            //     }
            // }

            if (isset($buyingCandle->confirmTradeTimestamp)) {
                $tradeMarkers[] = [
                    'timestamp_pst' => $buyingCandle->confirmTradeTimestamp,
                    'color' => 'pink',
                    'text' => 'CT ' . $index + 1,
                    'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
                ];
            }
            if (isset($buyingCandle->lpIndex)) {
                $tradeMarkers[] = [
                    'timestamp_pst' => $buyingCandle->lpIndex,
                    'color' => 'orange',
                    'text' => 'LP ' . $index + 1,
                    'position' => $trade->position === 'LONG' ? 'belowBar' : 'aboveBar'
                ];
            }

            $tsAdjustment = 18000000;

            if (isset($buyingCandle->latestBearOb)) {
                $latestBearOb = $buyingCandle->latestBearOb;
                $lines[] = [
                    'x1' => $latestBearOb->timestamp + $tsAdjustment, // timestamp for first point
                    'y1' => $latestBearOb->top,         // price for first point
                    'x2' => $sellingCandle->timestamp_pst, // timestamp for second point
                    'y2' => $latestBearOb->top,         // price for second point
                    'color' => 'red',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Bear OB'
                ];
                $lines[] = [
                    'x1' => $latestBearOb->timestamp + $tsAdjustment, // timestamp for first point
                    'y1' => $latestBearOb->bottom,         // price for first point
                    'x2' => $sellingCandle->timestamp_pst, // timestamp for second point
                    'y2' => $latestBearOb->bottom,         // price for second point
                    'color' => 'red',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Bear OB'
                ];
            }
            if (isset($buyingCandle->latestBullOb)) {
                $latestBullOb = $buyingCandle->latestBullOb;
                $lines[] = [
                    'x1' => $latestBullOb->timestamp + $tsAdjustment, // timestamp for first point
                    'y1' => $latestBullOb->top,         // price for first point
                    'x2' => $sellingCandle->timestamp_pst, // timestamp for second point
                    'y2' => $latestBullOb->top,         // price for second point
                    'color' => 'green',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Bull OB'
                ];
                $lines[] = [
                    'x1' => $latestBullOb->timestamp + $tsAdjustment, // timestamp for first point
                    'y1' => $latestBullOb->bottom,         // price for first point
                    'x2' => $sellingCandle->timestamp_pst, // timestamp for second point
                    'y2' => $latestBullOb->bottom,         // price for second point
                    'color' => 'green',  // red color
                    'thickness' => 2,      // line thickness
                    'title' => 'Bull OB'
                ];
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
            'otherMarkers' => $otherMarkers,
            'lines' => $lines,


        ]);
    }




    public function showTrends($market, Request $request)
    {


        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '15m');
        $data = BinanceApiService::getCandleStickDataExtended($symbol, $interval, 1000, null, 'FUTURE');


        $data1h = BinanceApiService::getCandleStickDataExtended($symbol, '1h', 1000, null, 'FUTURE');

        $openingMarkers = [];
        $lines = [];
        $equations = [];
        $trades = [];


        $waitingCandles = 0;

        $sLevels = [];
        $lastFvg = null;

        foreach ($data as $index => &$candle) {




            if ($index < 200 || $index > count($data) - 10) {
                continue;
            }

            if ($waitingCandles) {
                $waitingCandles--;
                continue;
            }





            $datah = CommonHelpers::filterCandlestickData($data1h, null, $data[$index]['binance_timestamp']);


            $indexh = count($datah) - 2;

            $fvg = CommonHelpers::getLatestFVGatIndex($datah, $indexh, 'body');



            $lastFvg = $fvg;



            if ($lastFvg) {
                $fvg = $lastFvg;
                // Active FVG's
                $trades[] = [
                    'type' => 'LONG',
                    'startTimestamp' => $data[$index]['timestamp_pst'], // Unix milliseconds
                    'endTimestamp' => $data[$index + 1]['timestamp_pst'],
                    'entryPrice' => $fvg['bottom'],
                    'tp' => $fvg['top'],
                    'sl' => $fvg['bottom'],
                    'tpColor' =>   $fvg['type'] === 'bullish' ?  'rgba(0, 203, 27, 0.3)' : 'rgba(203, 61, 0, 0.3)',
                    'slColor' => 'rgba(255, 0, 0, 0)',
                    'markers' => false,
                    'profit' => 0,
                ];



                $bodyMin = min($data[$index]['close'], $data[$index]['open']);
                $bodyMax = max($data[$index]['close'], $data[$index]['open']);




                if ($fvg['type'] === 'bullish') {

                    if (
                        $data[$index]['close'] > $fvg['top']
                        && $data[$index]['low'] < $fvg['top']
                    ) {

                        $openingMarkers[] = [
                            'timestamp_pst' => $data[$index]['timestamp_pst'],
                            'color' => 'green',
                            'text' => 'LONG ',
                            'position' => 'belowBar'
                        ];
                        $waitingCandles = 10;
                    }
                } else {

                    if (
                        $data[$index]['close'] < $fvg['bottom']
                        && $data[$index]['high'] > $fvg['bottom']
                    ) {


                        $openingMarkers[] = [
                            'timestamp_pst' => $data[$index]['timestamp_pst'],
                            'color' => 'red',
                            'text' => 'SHORT ',
                            'position' => 'aboveBar'
                        ];
                        $waitingCandles = 10;
                    }
                }
            }
        }











        return view('MarketTrends.index', ['data' => $data, 'pageSlug' => 'MarketTrends' . $market, 'openingMarkers' => $openingMarkers, 'lines' => $lines, 'trades' => $trades, 'equations' => $equations, 'symbol' => $symbol, 'interval' => $interval]);
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



















    public static function detectConsolidation($data, $index, $options = [])
    {
        // Default configuration
        $config = array_merge([
            'lookback_period' => 20,           // Number of candles to look back
            'price_threshold_percent' => 2.0,  // Max price range for consolidation (%)
            'volume_threshold' => 0.8,         // Volume ratio threshold
            'min_consolidation_candles' => 5,  // Minimum candles for valid consolidation
            'atr_threshold' => 0.7,           // ATR ratio threshold
            'adx_threshold' => 25,            // ADX threshold for trending vs sideways
            'sensitivity' => 'medium'         // 'low', 'medium', 'high'
        ], $options);

        // Adjust thresholds based on sensitivity
        switch ($config['sensitivity']) {
            case 'low':
                $config['price_threshold_percent'] *= 0.7;
                $config['atr_threshold'] *= 0.8;
                break;
            case 'high':
                $config['price_threshold_percent'] *= 1.3;
                $config['atr_threshold'] *= 1.2;
                break;
                // medium uses default values
        }

        $lookback = $config['lookback_period'];
        $startIndex = max(0, $index - $lookback);
        $endIndex = min(count($data) - 1, $index);

        // Validate input
        if ($index < $lookback || $index >= count($data)) {
            return [
                'is_consolidated' => false,
                'confidence' => 0,
                'upper_bound' => null,
                'lower_bound' => null,
                'error' => 'Insufficient data or invalid index'
            ];
        }

        $consolidationScore = 0;
        $maxScore = 100;

        // 1. Price Range Analysis (30 points)
        $priceRangeScore = self::analyzePriceRange($data, $startIndex, $endIndex, $config);
        $consolidationScore += $priceRangeScore;

        // 2. Volatility Analysis using ATR (25 points)
        $atrScore = self::analyzeVolatility($data, $index, $config);
        $consolidationScore += $atrScore;

        // 3. Volume Analysis (20 points)
        $volumeScore = self::analyzeVolume($data, $startIndex, $endIndex, $config);
        $consolidationScore += $volumeScore;

        // 4. ADX Analysis (15 points)
        $adxScore = self::analyzeADX($data, $index, $config);
        $consolidationScore += $adxScore;

        // 5. Bollinger Band Analysis (10 points)
        $bbScore = self::analyzeBollingerBands($data, $index, $config);
        $consolidationScore += $bbScore;

        // Calculate consolidation bounds
        $bounds = self::calculateConsolidationBounds($data, $startIndex, $endIndex);

        // Determine if market is consolidated
        $confidenceThreshold = 60; // Minimum score for consolidation
        $isConsolidated = $consolidationScore >= $confidenceThreshold;

        return [
            'is_consolidated' => $isConsolidated,
            'confidence' => round($consolidationScore, 2),
            'upper_bound' => $bounds['upper'],
            'lower_bound' => $bounds['lower'],
            'consolidation_range_percent' => $bounds['range_percent'],
            'duration_candles' => $endIndex - $startIndex + 1,
            'scores' => [
                'price_range' => $priceRangeScore,
                'volatility' => $atrScore,
                'volume' => $volumeScore,
                'adx' => $adxScore,
                'bollinger' => $bbScore
            ]
        ];
    }

    /**
     * Analyze price range compression
     */
    public static function analyzePriceRange($data, $startIndex, $endIndex, $config)
    {
        $highs = [];
        $lows = [];
        $closes = [];

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $highs[] = $data[$i]['high'];
            $lows[] = $data[$i]['low'];
            $closes[] = $data[$i]['close'];
        }

        $highest = max($highs);
        $lowest = min($lows);
        $avgClose = array_sum($closes) / count($closes);

        // Calculate range as percentage
        $rangePercent = (($highest - $lowest) / $avgClose) * 100;

        // Score based on how compressed the range is
        if ($rangePercent <= $config['price_threshold_percent'] * 0.5) {
            return 30; // Very tight range
        } elseif ($rangePercent <= $config['price_threshold_percent'] * 0.75) {
            return 22; // Tight range
        } elseif ($rangePercent <= $config['price_threshold_percent']) {
            return 15; // Moderate range
        } elseif ($rangePercent <= $config['price_threshold_percent'] * 1.25) {
            return 8; // Slightly wide range
        } else {
            return 0; // Too wide for consolidation
        }
    }

    /**
     * Analyze volatility using available moving averages as ATR proxy
     */
    public static function analyzeVolatility($data, $index, $config)
    {
        if (!isset($data[$index]['adx'])) {
            return 0;
        }

        // Calculate recent volatility using high-low ranges
        $ranges = [];
        $lookback = min(14, $index);

        for ($i = max(0, $index - $lookback); $i <= $index; $i++) {
            $range = (($data[$i]['high'] - $data[$i]['low']) / $data[$i]['close']) * 100;
            $ranges[] = $range;
        }

        $currentRange = end($ranges);
        $avgRange = array_sum($ranges) / count($ranges);

        // Score based on current volatility vs average
        $volatilityRatio = $currentRange / $avgRange;

        if ($volatilityRatio <= $config['atr_threshold'] * 0.5) {
            return 25; // Very low volatility
        } elseif ($volatilityRatio <= $config['atr_threshold'] * 0.75) {
            return 20; // Low volatility
        } elseif ($volatilityRatio <= $config['atr_threshold']) {
            return 15; // Moderate volatility
        } elseif ($volatilityRatio <= $config['atr_threshold'] * 1.25) {
            return 8; // Slightly high volatility
        } else {
            return 0; // High volatility
        }
    }

    /**
     * Analyze volume patterns
     */
    public static function analyzeVolume($data, $startIndex, $endIndex, $config)
    {
        $volumes = [];

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $volumes[] = $data[$i]['volume'];
        }

        $avgVolume = array_sum($volumes) / count($volumes);

        // Check if we have volume MA data
        $currentVolumeMA = isset($data[$endIndex]['volumeMA10']) ? $data[$endIndex]['volumeMA10'] : $avgVolume;
        $volumeRatio = $avgVolume / $currentVolumeMA;

        // Lower volume during consolidation is typical
        if ($volumeRatio <= $config['volume_threshold'] * 0.6) {
            return 20; // Very low volume
        } elseif ($volumeRatio <= $config['volume_threshold'] * 0.8) {
            return 15; // Low volume
        } elseif ($volumeRatio <= $config['volume_threshold']) {
            return 10; // Moderate volume
        } else {
            return 5; // Higher volume (less consolidation-like)
        }
    }

    /**
     * Analyze ADX for trend strength
     */
    public static function analyzeADX($data, $index, $config)
    {
        if (!isset($data[$index]['adx'])) {
            return 0;
        }

        $adx = $data[$index]['adx'];

        // Lower ADX indicates sideways/consolidating market
        if ($adx <= $config['adx_threshold'] * 0.6) {
            return 15; // Very weak trend (strong consolidation signal)
        } elseif ($adx <= $config['adx_threshold'] * 0.8) {
            return 12; // Weak trend
        } elseif ($adx <= $config['adx_threshold']) {
            return 8; // Moderate trend
        } else {
            return 0; // Strong trend (not consolidating)
        }
    }

    /**
     * Analyze Bollinger Band width
     */
    public static function analyzeBollingerBands($data, $index, $config)
    {
        if (!isset($data[$index]['bb_upper']) || !isset($data[$index]['bb_lower']) || !isset($data[$index]['bb_middle'])) {
            return 0;
        }

        $upper = $data[$index]['bb_upper'];
        $lower = $data[$index]['bb_lower'];
        $middle = $data[$index]['bb_middle'];

        // Calculate band width as percentage
        $bandWidth = (($upper - $lower) / $middle) * 100;

        // Calculate average band width over recent periods
        $bandWidths = [];
        $lookback = min(20, $index);

        for ($i = max(0, $index - $lookback); $i <= $index; $i++) {
            if (isset($data[$i]['bb_upper']) && isset($data[$i]['bb_lower']) && isset($data[$i]['bb_middle'])) {
                $bw = (($data[$i]['bb_upper'] - $data[$i]['bb_lower']) / $data[$i]['bb_middle']) * 100;
                $bandWidths[] = $bw;
            }
        }

        if (empty($bandWidths)) {
            return 0;
        }

        $avgBandWidth = array_sum($bandWidths) / count($bandWidths);
        $bandWidthRatio = $bandWidth / $avgBandWidth;

        // Narrow bands indicate consolidation
        if ($bandWidthRatio <= 0.7) {
            return 10; // Very narrow bands
        } elseif ($bandWidthRatio <= 0.85) {
            return 7; // Narrow bands
        } elseif ($bandWidthRatio <= 1.0) {
            return 4; // Moderate bands
        } else {
            return 0; // Wide bands
        }
    }

    /**
     * Calculate consolidation bounds
     */
    public static function calculateConsolidationBounds($data, $startIndex, $endIndex)
    {
        $highs = [];
        $lows = [];
        $closes = [];

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $highs[] = $data[$i]['high'];
            $lows[] = $data[$i]['low'];
            $closes[] = $data[$i]['close'];
        }

        $upper = max($highs);
        $lower = min($lows);
        $avgClose = array_sum($closes) / count($closes);

        $rangePercent = (($upper - $lower) / $avgClose) * 100;

        return [
            'upper' => round($upper, 6),
            'lower' => round($lower, 6),
            'range_percent' => round($rangePercent, 2)
        ];
    }













    /**
     * detect_zones_at_index
     *
     * Detects demand & supply zones for a given candle index in a dataset.
     *
     * @param array $data  Array of candle data (your provided structure)
     * @param int   $index Current index of candle to check
     * @param array $opts  Optional settings
     * @return array ['supply' => [...], 'demand' => [...]]
     */
    public static function detect_zones_at_index(array $data, int $index, array $opts = []): array
    {
        $swing_w = $opts['swing_width'] ?? 2;
        $min_impulse_pct = $opts['min_impulse_pct'] ?? 0.8;
        $buffer_pct = $opts['buffer_pct'] ?? 0.15;

        $n = count($data);
        $zones = ['supply' => [], 'demand' => []];

        // Helper: percent change
        $pct = function ($from, $to) {
            return $from != 0 ? (($to - $from) / $from) * 100.0 : 0;
        };

        if ($index < $swing_w || $index >= $n - $swing_w) {
            return $zones; // not enough candles around
        }

        $hi = $data[$index]['high'];
        $lo = $data[$index]['low'];
        $op = $data[$index]['open'];
        $cl = $data[$index]['close'];

        // --- SWING HIGH CHECK ---
        $isSwingHigh = true;
        for ($k = 1; $k <= $swing_w; $k++) {
            if ($data[$index - $k]['high'] >= $hi || $data[$index + $k]['high'] >= $hi) {
                $isSwingHigh = false;
                break;
            }
        }
        if ($isSwingHigh) {
            $futureIdx = min($n - 1, $index + $swing_w + 1);
            $futureClose = $data[$futureIdx]['close'];
            $movePct = $pct($hi, $futureClose); // should be negative
            if ($movePct < 0 && abs($movePct) >= $min_impulse_pct) {
                $bodyHigh = max($op, $cl);
                $buffer = ($hi - $bodyHigh) * $buffer_pct;
                $zones['supply'][] = [
                    'top'         => $hi,
                    'bottom'      => $bodyHigh + $buffer,
                    'pivot_index' => $index,
                    'pivot_time'  => $data[$index]['timestamp'],
                    'strength_pct' => abs($movePct)
                ];
            }
        }

        // --- SWING LOW CHECK ---
        $isSwingLow = true;
        for ($k = 1; $k <= $swing_w; $k++) {
            if ($data[$index - $k]['low'] <= $lo || $data[$index + $k]['low'] <= $lo) {
                $isSwingLow = false;
                break;
            }
        }
        if ($isSwingLow) {
            $futureIdx = min($n - 1, $index + $swing_w + 1);
            $futureClose = $data[$futureIdx]['close'];
            $movePct = $pct($lo, $futureClose); // should be positive
            if ($movePct > 0 && $movePct >= $min_impulse_pct) {
                $bodyLow = min($op, $cl);
                $buffer = ($bodyLow - $lo) * $buffer_pct;
                $zones['demand'][] = [
                    'top'         => $bodyLow - $buffer,
                    'bottom'      => $lo,
                    'pivot_index' => $index,
                    'pivot_time'  => $data[$index]['timestamp'],
                    'strength_pct' => $movePct
                ];
            }
        }

        return $zones;
    }
}









class OrderBlockDetector
{
    private $mslen = 5; // Market structure length (equivalent to mslen in Pine Script)
    private $atrPeriod = 200; // ATR calculation period
    private $obmode = "Length"; // "Length" or "Full"
    private $len = 5; // Length parameter for order block construction

    public function __construct($mslen = 5, $atrPeriod = 200, $obmode = "Length", $len = 5)
    {
        $this->mslen = $mslen;
        $this->atrPeriod = $atrPeriod;
        $this->obmode = $obmode;
        $this->len = $len;
    }

    /**
     * Get recent order blocks up to the specified candle index
     * 
     * @param array $data - Array of candlestick data
     * @param int $index - Current index to analyze up to
     * @param int $maxOrderBlocks - Maximum number of order blocks to return (default: 5)
     * @return array - Array of order blocks with bull/bear classification
     */
    public function getRecentOrderBlocks($data, $index, $maxOrderBlocks = 5)
    {
        if ($index < $this->mslen * 2 + $this->atrPeriod) {
            return ['bull' => [], 'bear' => []]; // Not enough data
        }

        // Initialize market structure state
        $ms = $this->initializeMarketStructure($data, $index);

        // Calculate ATR for order block positioning
        $atr = $this->calculateATR($data, $index);

        // Find order blocks
        $bullOrderBlocks = [];
        $bearOrderBlocks = [];

        // Track market structure changes and identify order block formation points
        $structureChanges = $this->findStructureChanges($data, $index);

        foreach ($structureChanges as $change) {
            if ($change['type'] === 'choch_bull_to_bear') {
                // Bullish order block formed
                $ob = $this->createBullOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bullOrderBlocks[] = $ob;
                }
            } elseif ($change['type'] === 'choch_bear_to_bull') {
                // Bearish order block formed  
                $ob = $this->createBearOrderBlock($data, $change, $atr);
                if ($ob) {
                    $bearOrderBlocks[] = $ob;
                }
            }
        }

        // Sort by recency and limit results
        usort($bullOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });

        usort($bearOrderBlocks, function ($a, $b) {
            return $b['loc'] - $a['loc']; // Most recent first
        });


        $bullZone = [];
        $bearZone = [];
        foreach (array_slice($bullOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bullZone[] = $zone;
            }
        }

        foreach (array_slice($bearOrderBlocks, 0, $maxOrderBlocks)  as $zone) {


            $startIndex = (count($data) - 1) - OpeningConditionServiceLive::getIndexDiffFromTimestamps($zone['timestamp'], $data[count($data) - 1]['binance_timestamp'], '15m');

            if (
                (min($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && min($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && min($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && min($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )
                ||
                (max($data[$startIndex]['open'], $data[$startIndex]['close']) <= $zone['top']
                    && max($data[$startIndex]['open'], $data[$startIndex]['close']) >= $zone['bottom']

                    &&  max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) <= $zone['top']
                    && max($data[$startIndex + 1]['open'], $data[$startIndex + 1]['close']) >= $zone['bottom']


                    &&  max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) <= $zone['top']
                    && max($data[$startIndex + 2]['open'], $data[$startIndex + 2]['close']) >= $zone['bottom']
                )

            ) {
                $bearZone[] = $zone;
            }
        }

        return [
            'bull' => $bullZone,
            'bear' => $bearZone
        ];
    }

    private function initializeMarketStructure($data, $index)
    {
        return [
            'trend' => 0, // 1 for uptrend, -1 for downtrend, 0 for initial
            'start' => 0, // Market structure state
            'choch' => null, // Change of character level
            'bos' => null, // Break of structure level
            'main' => null, // Current main level being tracked
            'loc' => 0, // Location of last structure change
            'temp' => 0, // Temporary location tracker
        ];
    }

    private function calculateATR($data, $index, $period = null)
    {
        if ($period === null) {
            $period = min($this->atrPeriod, $index);
        }

        $trValues = [];
        $startIdx = max(1, $index - $period + 1);

        for ($i = $startIdx; $i <= $index; $i++) {
            $high = $data[$i]['high'];
            $low = $data[$i]['low'];
            $prevClose = $data[$i - 1]['close'];

            $tr = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
            $trValues[] = $tr;
        }

        return array_sum($trValues) / count($trValues);
    }

    private function findStructureChanges($data, $index)
    {
        $changes = [];
        $ms = $this->initializeMarketStructure($data, $index);

        // Find pivot highs and lows
        $pivots = $this->findPivots($data, $index);

        // Analyze structure changes
        $trend = 0; // 0 = initial, 1 = up, -1 = down
        $lastHigh = null;
        $lastLow = null;

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            $current = $data[$i];

            // Check for pivot highs and lows
            if ($this->isPivotHigh($data, $i)) {
                if ($lastHigh !== null && $current['high'] < $lastHigh) {
                    // Lower high - potential trend change to bearish
                    if ($trend === 1) {
                        $changes[] = [
                            'type' => 'choch_bull_to_bear',
                            'index' => $i,
                            'price' => $current['high'],
                            'timestamp' => $current['timestamp'],
                            'prev_high' => $lastHigh
                        ];
                        $trend = -1;
                    }
                } elseif ($lastHigh !== null && $current['high'] > $lastHigh) {
                    // Higher high - continuation or new bullish trend
                    if ($trend !== 1) {
                        $trend = 1;
                    }
                }
                $lastHigh = $current['high'];
            }

            if ($this->isPivotLow($data, $i)) {
                if ($lastLow !== null && $current['low'] > $lastLow) {
                    // Higher low - potential trend change to bullish
                    if ($trend === -1) {
                        $changes[] = [
                            'type' => 'choch_bear_to_bull',
                            'index' => $i,
                            'price' => $current['low'],
                            'timestamp' => $current['timestamp'],
                            'prev_low' => $lastLow
                        ];
                        $trend = 1;
                    }
                } elseif ($lastLow !== null && $current['low'] < $lastLow) {
                    // Lower low - continuation or new bearish trend
                    if ($trend !== -1) {
                        $trend = -1;
                    }
                }
                $lastLow = $current['low'];
            }
        }

        return $changes;
    }

    private function findPivots($data, $index)
    {
        $pivots = ['highs' => [], 'lows' => []];

        for ($i = $this->mslen; $i <= $index - $this->mslen; $i++) {
            if ($this->isPivotHigh($data, $i)) {
                $pivots['highs'][] = ['index' => $i, 'price' => $data[$i]['high']];
            }
            if ($this->isPivotLow($data, $i)) {
                $pivots['lows'][] = ['index' => $i, 'price' => $data[$i]['low']];
            }
        }

        return $pivots;
    }

    private function isPivotHigh($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentHigh = $data[$index]['high'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        // Check right side  
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['high'] >= $currentHigh) {
                return false;
            }
        }

        return true;
    }

    private function isPivotLow($data, $index)
    {
        if ($index < $this->mslen || $index >= count($data) - $this->mslen) {
            return false;
        }

        $currentLow = $data[$index]['low'];

        // Check left side
        for ($i = $index - $this->mslen; $i < $index; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        // Check right side
        for ($i = $index + 1; $i <= $index + $this->mslen; $i++) {
            if ($data[$i]['low'] <= $currentLow) {
                return false;
            }
        }

        return true;
    }

    private function createBullOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the highest point before the structure change
        $highestIdx = $this->findHighest($data, max(0, $idx - 20), $idx);
        if ($highestIdx === null) return null;

        $candle = $data[$highestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust bottom based on mode
        if ($this->obmode === "Length") {
            $adjustedBottom = $candle['low'] + ($atr / (5 / $this->len));
            $bottom = min($adjustedBottom, $candle['high']) > $candle['low'] ? $candle['low'] : $adjustedBottom;
        }

        return [
            'type' => 'bull',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $highestIdx,
            'timestamp' => $candle['timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function createBearOrderBlock($data, $change, $atr)
    {
        $idx = $change['index'];
        if ($idx >= count($data)) return null;

        // Find the lowest point before the structure change  
        $lowestIdx = $this->findLowest($data, max(0, $idx - 20), $idx);
        if ($lowestIdx === null) return null;

        $candle = $data[$lowestIdx];
        $top = $candle['high'];
        $bottom = $candle['low'];

        // Adjust top based on mode
        if ($this->obmode === "Length") {
            $adjustedTop = $candle['high'] - ($atr / (5 / $this->len));
            $top = max($adjustedTop, $candle['low']) < $candle['high'] ? $candle['high'] : $adjustedTop;
        }

        return [
            'type' => 'bear',
            'top' => $top,
            'bottom' => $bottom,
            'avg' => ($top + $bottom) / 2,
            'loc' => $lowestIdx,
            'timestamp' => $candle['timestamp'],
            'volume' => $candle['volume'],
            'isBreaker' => false,
            'active' => true
        ];
    }

    private function findHighest($data, $startIdx, $endIdx)
    {
        $highest = null;
        $highestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($highest === null || $data[$i]['high'] > $highest) {
                $highest = $data[$i]['high'];
                $highestIdx = $i;
            }
        }

        return $highestIdx;
    }

    private function findLowest($data, $startIdx, $endIdx)
    {
        $lowest = null;
        $lowestIdx = null;

        for ($i = $startIdx; $i <= $endIdx; $i++) {
            if ($i >= count($data)) break;
            if ($lowest === null || $data[$i]['low'] < $lowest) {
                $lowest = $data[$i]['low'];
                $lowestIdx = $i;
            }
        }

        return $lowestIdx;
    }
}
