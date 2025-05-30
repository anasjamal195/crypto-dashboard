<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\OrderBookStrategy;
use App\Services\PatternDetector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;

class AnalysisToolsController extends Controller
{
    public function orderBookTool()
    {

        $pageSlug = 'orderBookTool';


        $symbol = request()->input('symbol', 'BTCUSDT');
        $interval = request()->input('interval', '5m');
        $depth = request()->input('depth', 500);

        $coinData = [];
        $snapshot = null;

        try {
            if ($symbol && $depth) {
                // Fetching Data from external server
                $apiPointerUrl = 'https://xnfts.shop/load_balancer/orderBook.php';
                $orderBookData = BinanceApiService::getOrderBook($symbol, $depth, $apiPointerUrl);


                if (!$orderBookData) {
                    return abort(500);
                }

                if (isset($orderBookData['error'])) {
                    return redirect()->back()->withError('Rate Limit Exceeded, please wait!');
                }
                $orderBookStrategy = new OrderBookStrategy();
                // Analyze the order book
                $analysis = $orderBookStrategy->analyzeOrderBook($symbol, $depth);
                if (!$analysis['success']) {
                    return abort(500);
                }

                // Extract data from analysis
                $analysisData = $analysis['analysis'];
                $signals = $analysis['signals'];

                // Create the snapshot record
                $snapshot = OrderBookSnapshot::create([
                    'symbol' => $symbol,
                    'snapshot_time' => Carbon::now(),
                    'depth' => $depth,
                    'raw_data' => $orderBookData,
                    'bid_volume' => $analysisData['bid_volume'],
                    'ask_volume' => $analysisData['ask_volume'],
                    'volume_imbalance' => $analysisData['volume_imbalance'],
                    'highest_bid' => isset($orderBookData['bids'][0]) ? $orderBookData['bids'][0][0] : null,
                    'lowest_ask' => isset($orderBookData['asks'][0]) ? $orderBookData['asks'][0][0] : null,
                    'spread' => isset($orderBookData['asks'][0]) && isset($orderBookData['bids'][0])
                        ? (float)$orderBookData['asks'][0][0] - (float)$orderBookData['bids'][0][0]
                        : null,
                    'support_levels' => $analysisData['support_levels'],
                    'resistance_levels' => $analysisData['resistance_levels'],
                    'thin_liquidity_areas' => $analysisData['thin_liquidity_areas'],
                    'signal' => $signals['recommendation'],
                    'long_strength' => $signals['long']['strength'],
                    'short_strength' => $signals['short']['strength'],
                    'long_entry_points' => $signals['long']['entry_points'],
                    'short_entry_points' => $signals['short']['entry_points'],
                    'type' => 'm',
                ]);


                $snapshotTime = $snapshot->snapshot_time;

                $coinData = BinanceApiService::getCandleStickData($snapshot->symbol, $interval, 1000, null, 'FUTURE');
            } else {
                $snapshot = null;
                $coinData = [];
            }
        } catch (\Throwable $th) {
            dd($th);
            Session::flash('error', 'Error fetching coin data...');
        }




        return view('analysis-tools.order-book-tool', compact('snapshot', 'pageSlug', 'coinData', 'interval', 'symbol'));
    }


    public function volumeTool()
    {

        $pageSlug = 'volumeTool';
        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 1000);

        $coinData = [];
        $volumeSignals = [];


        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true, null, $limit);
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }


        return view('analysis-tools.volume-tool', compact('volumeSignals', 'symbol', 'interval', 'pageSlug', 'coinData'));
    }


    public function bollingerBandTool()
    {

        $pageSlug = 'bollingerBandTool';

        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 1000);


        $coinData = [];
        $volumeSignals = [];

        $markers = [];

        $bbAnalysis = [];
        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);

            $lastIndex = count($coinData) - 1;
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($coinData, $lastIndex, 20);
            if ($bbAnalysis['signal'] === 'LONG') {
                $markers[] = [
                    'timestamp_pst' => $coinData[$lastIndex]['timestamp_pst'],
                    'color' => '#26a69a',
                    'text' => 'B',
                ];
            } else if ($bbAnalysis['signal'] === 'SHORT') {
                $markers[] = [
                    'timestamp_pst' => $coinData[$lastIndex]['timestamp_pst'],
                    'color' => '#ef5350',
                    'text' => 'S',
                ];
            } else {
                $markers[] = [
                    'timestamp_pst' => $coinData[$lastIndex]['timestamp_pst'],
                    'color' => '#9e9e9e',
                    'text' => 'N',
                ];
            }
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
        }




        return view('analysis-tools.bollinger-band-tool', compact('symbol', 'markers', 'interval', 'pageSlug', 'coinData', 'bbAnalysis'));
    }


    public function technicalTrendTool()
    {

        $pageSlug = 'technicalTrendTool';

        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 1000);

        $coinData = [];
        $trendDetails = [];


        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;
            $trendDetails = CommonHelpers::detectTrend($coinData, $lastIndex, 60, 20);
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }

        // dd($trendDetails);


        return view('analysis-tools.technical-trend-tool', compact('symbol', 'interval', 'pageSlug', 'coinData', 'trendDetails'));
    }


    public function chartPatternTool()
    {

        $pageSlug = 'chartPatternTool';

        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 1000);

        $coinData = [];
        $patternDetails = [];


        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;
            $patternDetails = PatternDetector::analyzeEntry($coinData, $lastIndex);
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }






        return view('analysis-tools.chart-pattern-tool', compact('symbol', 'interval', 'pageSlug', 'coinData', 'patternDetails'));
    }

    public function indicatorComparisonTool()
    {

        $pageSlug = 'indicatorComparisonTool';


        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 1000);

        $coinData = [];
        $currentCandle = [];
        $prevCandle = [];


        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;
            $currentCandle = $coinData[$lastIndex];
            $prevCandle = $coinData[$lastIndex - 1];
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }


        return view('analysis-tools.indicator-comparison-tool', compact('symbol', 'interval', 'pageSlug', 'coinData', 'prevCandle', 'currentCandle'));
    }
}
