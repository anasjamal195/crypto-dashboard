<?php

namespace App\Http\Controllers;

use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\OrderBookStrategy;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderBookSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderBookSnapshot::query();
        $pageSlug = 'Order Book';
        // Apply filters if provided
        if ($request->filled('symbol')) {
            $query->where('symbol', $request->symbol);
        }

        if ($request->filled('signal')) {
            $query->where('signal', $request->signal);
        }
        if ($request->filled('strength')) {
            $query->where(function ($query) use ($request) {
                $query->where('long_strength', $request->strength)
                    ->orWhere('short_strength', $request->strength);
            });
        }
        if ($request->filled('date_from')) {
            $query->where('snapshot_time', '>=', Carbon::parse(request('date_from'))->format('Y-m-d H:i:s'));
        }

        if ($request->filled('date_to')) {
            $query->where('snapshot_time', '<=', Carbon::parse(request('date_to'))->format('Y-m-d H:i:s'));
        }
        // Ignore Neutral Signal
        $query->whereIn('signal', ['LONG', 'SHORT']);


        // Get distinct symbols and signals for filter dropdowns
        $symbols = OrderBookSnapshot::distinct()->pluck('symbol');
        $signals = OrderBookSnapshot::distinct()->pluck('signal');

        // Paginate results
        $snapshots = $query->latest('snapshot_time')->paginate(100);

        return view('order-book.index', compact('snapshots', 'symbols', 'signals', 'pageSlug'));
    }


    public function overview(Request $request)
    {
        $query = OrderBookSnapshot::query();
        $pageSlug = 'Order Book Overview';


        $snapshots = OrderBookSnapshot::selectRaw("
        symbol, 
        COUNT(CASE WHEN `signal` = 'LONG' THEN 1 END) AS long_count, 
        COUNT(CASE WHEN `signal` = 'SHORT' THEN 1 END) AS short_count,
        MAX(snapshot_time) AS latest_snapshot_time
    ")
            ->whereIn('signal', ['LONG', 'SHORT'])
            ->groupBy('symbol')
            ->latest('latest_snapshot_time')
            ->get();





        return view('order-book.overview', compact('snapshots', 'pageSlug'));
    }
    public function show($id)
    {
        $pageSlug = 'Order Book Details';
        $snapshot = OrderBookSnapshot::findOrFail($id);
        $snapshotTime = $snapshot->snapshot_time;

        // Convert to UTC and get UNIX timestamp in seconds
        $unixTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $snapshotTime, 'Asia/Karachi')
            ->setTimezone('UTC')
            ->timestamp;

        // Round down to the nearest 5-minute mark in seconds
        $roundedUnixTimestamp = floor($unixTimestamp / 300) * 300;

        // Convert to milliseconds
        $roundedUnixTimestampMs = $roundedUnixTimestamp * 1000;

        $min5tomilis = 5 * 60 * 1000;

        $tp = request()->input('tp');
        $sl = request()->input('sl');

        $openPrice = 0;
        $coinData = BinanceApiService::getCandleStickData($snapshot->symbol, '5m', 500, $roundedUnixTimestampMs - ($min5tomilis * 100), 'FUTURE');
        foreach ($coinData as $index => &$candle) {


            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
            $candle['marketTrend'] = 'blue';
            if ($candle['binance_timestamp'] == $roundedUnixTimestampMs) {


                $candle['marketTrend'] = 'green';

                $openPrice = $candle['close'];
            }

            // Take Profit Check
            if ($tp && $openPrice) {
                if ($snapshot->signal === 'LONG') {
                    if ($candle['close'] >= ($openPrice * (1 + $tp / 100))) {
                        $candle['marketTrend'] = 'orange';
                        $tp = null;
                        $sl = null;
                        $openPrice = 0;
                    }
                }

                if ($snapshot->signal === 'SHORT') {
                    if ($candle['close'] <= ($openPrice * (1 - $tp / 100))) {
                        $candle['marketTrend'] = 'orange';
                        $tp = null;
                        $sl = null;
                        $openPrice = 0;
                    }
                }
            }

            // Stop Loss Check
            if ($sl && $openPrice) {
                if ($snapshot->signal === 'LONG') {
                    if ($candle['close'] <= ($openPrice * (1 - $sl / 100))) {
                        $candle['marketTrend'] = 'red';
                        $tp = null;
                        $sl = null;
                        $openPrice = 0;
                    }
                }

                if ($snapshot->signal === 'SHORT') {
                    if ($candle['close'] >= ($openPrice * (1 + $sl / 100))) {
                        $candle['marketTrend'] = 'red';
                        $tp = null;
                        $sl = null;
                        $openPrice = 0;
                    }
                }
            }
        }
        return view('order-book.show', compact('snapshot', 'pageSlug', 'coinData'));
    }
    public function checkStatus()
    {
        $pageSlug = 'Check Order Book Status';

        $symbol = request()->input('symbol');
        $depth = request()->input('depth', 100);



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
            ]);
            // ----------------------------------






            $snapshotTime = $snapshot->snapshot_time;

            // Convert to UTC and get UNIX timestamp in seconds
            $unixTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $snapshotTime, 'Asia/Karachi')
                ->setTimezone('UTC')
                ->timestamp;

            // Round down to the nearest 5-minute mark in seconds
            $roundedUnixTimestamp = floor($unixTimestamp / 300) * 300;

            // Convert to milliseconds
            $roundedUnixTimestampMs = $roundedUnixTimestamp * 1000;

            $min5tomilis = 5 * 60 * 1000;

            $tp = request()->input('tp');
            $sl = request()->input('sl');

            $openPrice = 0;
            $coinData = BinanceApiService::getCandleStickData($snapshot->symbol, '5m', 500, $roundedUnixTimestampMs - ($min5tomilis * 100), 'FUTURE');
            foreach ($coinData as $index => &$candle) {


                $candle['timestamp'] = $candle['timestamp'] / 1000;
                $date = new \DateTime("@{$candle['timestamp']}");
                $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
                $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
                $candle['marketTrend'] = 'blue';
                if ($candle['binance_timestamp'] == $roundedUnixTimestampMs) {


                    $candle['marketTrend'] = 'green';

                    $openPrice = $candle['close'];
                }

                // Take Profit Check
                if ($tp && $openPrice) {
                    if ($snapshot->signal === 'LONG') {
                        if ($candle['close'] >= ($openPrice * (1 + $tp / 100))) {
                            $candle['marketTrend'] = 'orange';
                            $tp = null;
                            $sl = null;
                            $openPrice = 0;
                        }
                    }

                    if ($snapshot->signal === 'SHORT') {
                        if ($candle['close'] <= ($openPrice * (1 - $tp / 100))) {
                            $candle['marketTrend'] = 'orange';
                            $tp = null;
                            $sl = null;
                            $openPrice = 0;
                        }
                    }
                }

                // Stop Loss Check
                if ($sl && $openPrice) {
                    if ($snapshot->signal === 'LONG') {
                        if ($candle['close'] <= ($openPrice * (1 - $sl / 100))) {
                            $candle['marketTrend'] = 'red';
                            $tp = null;
                            $sl = null;
                            $openPrice = 0;
                        }
                    }

                    if ($snapshot->signal === 'SHORT') {
                        if ($candle['close'] >= ($openPrice * (1 + $sl / 100))) {
                            $candle['marketTrend'] = 'red';
                            $tp = null;
                            $sl = null;
                            $openPrice = 0;
                        }
                    }
                }
            }
        } else {
            $snapshot = null;
            $coinData = null;
        }




        return view('order-book.check-status', compact('snapshot', 'pageSlug', 'coinData'));
    }
}
