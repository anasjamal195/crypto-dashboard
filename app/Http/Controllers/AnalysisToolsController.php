<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\InternalTrader\ReportService;
use App\Services\OrderBookStrategy;
use App\Services\PatternDetector;
use App\Services\SupportResistanceAnalyzer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;

class AnalysisToolsController extends Controller
{
    public function orderBookTool()
    {

        $pageSlug = 'orderBookTool';


        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

        $coinData = [];
        $snapshot = null;

        try {
            $depth = 1000;
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
            // dd($th);
            Session::flash('error', 'Error fetching coin data...');
        }




        return view('analysis-tools.order-book-tool', compact('snapshot', 'pageSlug', 'coinData', 'interval', 'symbol'));
    }


    public function volumeTool()
    {

        $pageSlug = 'volumeTool';


        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

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
        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);


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

        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

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

        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

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


        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

        $candle1 = request('candle1');
        $candle2 = request('candle2');


        $coinData = [];
        $currentCandle = [];
        $prevCandle = [];

        $markers = [];

        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;


            $index1 = $lastIndex - 1;
            $index2 = $lastIndex;

            if ($candle1) {
                $index1 = $lastIndex - ReportService::getIndexDiffFromTimestamps($candle1, $coinData[$lastIndex]['binance_timestamp'], $interval);
            }

            if ($candle2) {
                $index2 = $lastIndex - ReportService::getIndexDiffFromTimestamps($candle2, $coinData[$lastIndex]['binance_timestamp'], $interval);
            }
            $currentCandle = $coinData[$index2];
            $prevCandle = $coinData[$index1];

            $markers = [
                [
                    'timestamp_pst' => $coinData[$index1]['timestamp_pst'],
                    'color' => '#ef5350',
                    'text' => 'Previous Candle',
                    'position' => 'belowBarf'
                ],
                [
                    'timestamp_pst' => $coinData[$index2]['timestamp_pst'],
                    'color' => '#26a69a',
                    'text' => 'Current Candle',
                ]
            ];
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }

        return view('analysis-tools.indicator-comparison-tool', compact('symbol', 'interval', 'pageSlug', 'coinData', 'prevCandle', 'currentCandle', 'markers'));
    }



    public function supportResistanceTool()
    {
        $pageSlug = 'supportResistanceTool';

        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);

        // Fetch data
        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;
            $analyzer = new SupportResistanceAnalyzer($coinData, $lastIndex);
            $srAnalysis = $analyzer->analyze();

            $supportMarkers = array_map(function ($supportIndex) use ($coinData) {
                return [
                    'timestamp_pst' => $coinData[$supportIndex]['timestamp_pst'],
                    'color' => '#26a69a',
                    'text' => 'Support',
                    'position' => 'belowBar'
                ];
            }, $srAnalysis['sr_indexes']['support_indexes']);

            $resistanceMarkers = array_map(function ($resistanceIndex) use ($coinData) {
                return [
                    'timestamp_pst' => $coinData[$resistanceIndex]['timestamp_pst'],
                    'color' => '#ef5350',
                    'text' => 'Resistance',
                ];
            }, $srAnalysis['sr_indexes']['resistance_indexes']);

            $markers = array_merge($supportMarkers, $resistanceMarkers);
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            $coinData = [];
            $markers = [];
            $srAnalysis = [];
        }

        return view('analysis-tools.support-resistance-tool', compact('symbol', 'interval', 'pageSlug', 'coinData', 'markers', 'srAnalysis'));
    }




    public function analysisSummary()
    {

        $pageSlug = 'analysisSummary';

        // Request > Cookie > Default fallback
        $symbol = request('symbol') ?? request()->cookie('symbol', 'BTCUSDT');
        $interval = request('interval') ?? request()->cookie('interval', '5m');
        $limit = request('limit') ?? request()->cookie('limit', 1000);

        // Store these values in cookies for next request
        Cookie::queue('symbol', $symbol, 60 * 24 * 30); // 30 days
        Cookie::queue('interval', $interval, 60 * 24 * 30);
        Cookie::queue('limit', $limit, 60 * 24 * 30);
        $coinData = [];
        $analysis = [];

        try {
            $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);
            $lastIndex = count($coinData) - 1;

            // Analysis summary calculation

            // Order Book Analysis
            $depth = 500;
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
            $snapshot = [
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
                'long_entry_points' => json_encode($signals['long']['entry_points']),
                'short_entry_points' => json_encode($signals['short']['entry_points']),
                'type' => 'm',
            ];

            // Volume Analysis
            $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true, null, $limit);



            //  Bollinger Band
            $bbAnalysis = CommonHelpers::analyzeBollingerBandSwing($coinData, $lastIndex, 20);

            //  Technical Trend
            $trendDetails = CommonHelpers::detectTrend($coinData, $lastIndex, 60, 20);

            // Chart Pattern
            $patternDetails = PatternDetector::analyzeEntry($coinData, $lastIndex);



            //  Support Resistance Analysis
            $analyzer = new SupportResistanceAnalyzer($coinData, $lastIndex);
            $srAnalysis = $analyzer->analyze();

            $analysis = $this->consolidateAnalysis($snapshot, $bbAnalysis, $trendDetails, $patternDetails, $srAnalysis);
        } catch (\Throwable $th) {
            Session::flash('error', 'Error fetching coin data...');
            // dd($th);
        }


        return view('analysis-tools.analysis-summary', compact('symbol', 'interval', 'pageSlug', 'coinData', 'analysis'));
    }
    public function consolidateAnalysis($snapshot, $bbAnalysis, $trendDetails, $patternDetails, $srAnalysis)
    {

        // Extract key metrics
        $currentPrice = $snapshot['highest_bid'] ?? $srAnalysis['current_price'];
        $symbol = $snapshot['symbol'];
        $timestamp = $snapshot['snapshot_time'];

        // Initialize scoring system
        $longScore = 0;
        $shortScore = 0;
        $maxScore = 100;

        // 1. Order Book Analysis (25% weight)
        $obWeight = 25;
        if ($snapshot['signal'] === 'LONG') {
            $longScore += ($snapshot['long_strength'] / 10) * $obWeight;
        } elseif ($snapshot['signal'] === 'SHORT') {
            $shortScore += ($snapshot['short_strength'] / 10) * $obWeight;
        }

        // Volume imbalance consideration
        $volumeImbalance = $snapshot['volume_imbalance'];
        if ($volumeImbalance > 1.1) {
            $shortScore += 5; // More ask volume suggests selling pressure
        } elseif ($volumeImbalance < 0.9) {
            $longScore += 5; // More bid volume suggests buying pressure
        }

        // 2. Bollinger Bands Analysis (20% weight)
        $bbWeight = 20;
        $bbShortProb = $bbAnalysis['short_probability'];
        $bbLongProb = $bbAnalysis['long_probability'];

        $shortScore += ($bbShortProb / 100) * $bbWeight;
        $longScore += ($bbLongProb / 100) * $bbWeight;

        // Bollinger Band position analysis
        $percentB = $bbAnalysis['percent_b'];
        if ($percentB > 80) {
            $shortScore += 5; // Overbought
        } elseif ($percentB < 20) {
            $longScore += 5; // Oversold
        }

        // 3. Trend Analysis (30% weight)
        $trendWeight = 30;
        $trendStrength = $trendDetails['strength'];

        if ($trendDetails['trend'] === 'BULLISH') {
            $longScore += ($trendStrength / 100) * $trendWeight;
        } elseif ($trendDetails['trend'] === 'BEARISH') {
            $shortScore += ($trendStrength / 100) * $trendWeight;
        }

        // 4. Support/Resistance Analysis (25% weight)
        $srWeight = 25;
        $buySignalConfidence = 0;
        $sellSignalConfidence = 0;

        foreach ($srAnalysis['trading_signals'] as $signal) {
            if ($signal['type'] === 'buy') {
                $buySignalConfidence = max($buySignalConfidence, $signal['confidence']);
            } elseif ($signal['type'] === 'sell') {
                $sellSignalConfidence = max($sellSignalConfidence, $signal['confidence']);
            }
        }

        $longScore += ($buySignalConfidence / 100) * $srWeight;
        $shortScore += ($sellSignalConfidence / 100) * $srWeight;

        // Normalize scores
        $totalScore = $longScore + $shortScore;
        if ($totalScore > 0) {
            $longPercentage = ($longScore / $totalScore) * 100;
            $shortPercentage = ($shortScore / $totalScore) * 100;
        } else {
            $longPercentage = 50;
            $shortPercentage = 50;
        }

        // Determine final recommendation
        $recommendation = 'HOLD';
        $confidence = abs($longPercentage - $shortPercentage);

        if ($confidence >= 15) {
            if ($longPercentage > $shortPercentage) {
                $recommendation = 'LONG';
            } else {
                $recommendation = 'SHORT';
            }
        }

        // Risk assessment
        $riskLevel = 'MEDIUM';
        $spread = $snapshot['spread'];
        $bbWidth = $bbAnalysis['bb_width'];

        if ($spread > 1 || $bbWidth > 1000 || $confidence < 10) {
            $riskLevel = 'HIGH';
        } elseif ($spread < 0.1 && $bbWidth < 300 && $confidence > 25) {
            $riskLevel = 'LOW';
        }

        // Calculate entry and exit points
        $entryPoints = [];
        $exitPoints = [];

        if ($recommendation === 'LONG') {
            // Use support levels for long entries
            $longEntries = json_decode($snapshot['long_entry_points'], true);
            foreach ($longEntries as $entry) {
                $entryPoints[] = [
                    'price' => $entry['price'],
                    'type' => $entry['type'],
                    'confidence' => $entry['confidence']
                ];
            }

            // Use resistance levels for exits
            $resistanceLevels = $snapshot['resistance_levels'];
            foreach (array_slice($resistanceLevels, 0, 2) as $resistance) {
                $exitPoints[] = [
                    'price' => $resistance['price'],
                    'type' => 'resistance',
                    'confidence' => 4
                ];
            }
        } else {
            // Use resistance levels for short entries
            $shortEntries = json_decode($snapshot['short_entry_points'], true);

            foreach ($shortEntries as $entry) {
                $entryPoints[] = [
                    'price' => $entry['price'],
                    'type' => $entry['type'],
                    'confidence' => $entry['confidence']
                ];
            }

            // Use support levels for exits
            $supportLevels = $snapshot['support_levels'];
            foreach (array_slice($supportLevels, 0, 2) as $support) {
                $exitPoints[] = [
                    'price' => $support['price'],
                    'type' => 'support',
                    'confidence' => 4
                ];
            }
        }

        return [
            'symbol' => $symbol,
            'current_price' => $currentPrice,
            'timestamp' => $timestamp,
            'recommendation' => $recommendation,
            'confidence' => round($confidence, 2),
            'long_probability' => round($longPercentage, 2),
            'short_probability' => round($shortPercentage, 2),
            'risk_level' => $riskLevel,
            'entry_points' => $entryPoints,
            'exit_points' => $exitPoints,
            'individual_analysis' => [
                'order_book' => [
                    'signal' => $snapshot['signal'],
                    'long_strength' => $snapshot['long_strength'],
                    'short_strength' => $snapshot['short_strength'],
                    'volume_imbalance' => round($volumeImbalance, 3),
                    'spread' => $spread
                ],
                'bollinger_bands' => [
                    'signal' => $bbAnalysis['signal'],
                    'percent_b' => round($percentB, 2),
                    'width' => round($bbWidth, 2),
                    'is_contracting' => $bbAnalysis['is_contracting'],
                    'message' => $bbAnalysis['message']
                ],
                'trend_analysis' => [
                    'trend' => $trendDetails['trend'],
                    'strength' => round($trendStrength, 2),
                    'bullish_signals' => $trendDetails['bullish_count'],
                    'bearish_signals' => $trendDetails['bearish_count'],
                    'message' => $trendDetails['message']
                ],
                'support_resistance' => [
                    'buy_confidence' => round($buySignalConfidence, 2),
                    'sell_confidence' => round($sellSignalConfidence, 2),
                    'support_levels' => count($snapshot['support_levels']),
                    'resistance_levels' => count($snapshot['resistance_levels'])
                ]
            ],
            'market_conditions' => [
                'liquidity' => $snapshot['bid_volume'] + $snapshot['ask_volume'],
                'thin_areas' => count($snapshot['thin_liquidity_areas']),
                'volatility' => $riskLevel
            ]
        ];
    }
}
