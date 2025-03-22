<?php

namespace App\Http\Controllers;

use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
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

        if ($request->filled('date_from')) {
            $query->where('snapshot_time', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('snapshot_time', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Get distinct symbols and signals for filter dropdowns
        $symbols = OrderBookSnapshot::distinct()->pluck('symbol');
        $signals = OrderBookSnapshot::distinct()->pluck('signal');

        // Paginate results
        $snapshots = $query->paginate(100);

        return view('order-book.index', compact('snapshots', 'symbols', 'signals', 'pageSlug'));
    }

    public function show($id)
    {
        $pageSlug = 'Order Book Details';
        $snapshot = OrderBookSnapshot::findOrFail($id);
        $snapshotTime = '2025-03-21 04:31:06'; // Asia/Karachi time

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

                if ($snapshot->signal === 'LONG')
                    $candle['marketTrend'] = 'green';
                if ($snapshot->signal === 'SHORT')
                    $candle['marketTrend'] = 'red';

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
}
