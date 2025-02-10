<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class BinanceController extends Controller
{
    public function getCoinReport($market, Request $request)
    {
        // Fetch all unique symbols from the database
        $interval = $request->interval;
        $tradeData = DB::table('coin_reports')
            ->select(
                'symbol',
                DB::raw('COUNT(*) as total_entries'),                          // Total number of entries per symbol
                DB::raw('SUM(profit) as total_profit'),                        // Sum of profit per symbol
                DB::raw('AVG(profit) as average_profit'),                      // Average profit per symbol
                DB::raw('AVG(duration) as average_duration'),                  // Average duration per symbol
                DB::raw('SUM(duration) as total_duration'),                    // Total duration per symbol
                DB::raw('MAX(profit) as max_profit'),                          // Maximum profit per symbol
                DB::raw('MIN(profit) as min_profit'),                          // Minimum profit per symbol
                DB::raw('MAX(lowestPricePercentage) as max_lowestPrice'),                // Maximum of lowestPrice per symbol
                DB::raw('MIN(lowestPricePercentage) as min_lowestPrice'),                 // Minimum of lowestPrice per symbol
                DB::raw('MAX(created_at) as last_updated'),                 // Minimum of lowestPrice per symbol
            )
            ->where('market', $market)
            ->where('interval', $interval)
            ->groupBy('symbol')
            ->orderBy('total_entries', 'DESC')
            ->orderBy('last_updated', 'DESC')
            ->get();
        $pageSlug = 'CoinReport' . $market;

        $liquidatedCoins = DB::table('coin_reports')
            ->select('symbol', 'interval', 'market')
            ->distinct()
            ->whereRaw('liquidationPrice >= lowestPrice')  // Using whereRaw for correct column comparison
            ->get();


        // Extracting unique symbols, intervals, and markets
        $liquidatedSymbols = json_decode(json_encode($liquidatedCoins->pluck('symbol')->unique()), true);
        $liquidatedIntervals = json_decode(json_encode($liquidatedCoins->pluck('interval')->unique()), true);
        $liquidatedMarkets = json_decode(json_encode($liquidatedCoins->pluck('market')->unique()), true);

        return view('CoinReports.coin-report', compact('tradeData', 'pageSlug', 'interval', 'market', 'liquidatedSymbols', 'liquidatedIntervals', 'liquidatedMarkets'));
    }
    public function getCoinReportDetails($market, Request $request)
    {
        // Get the symbol from the request
        $symbol = $request->query('symbol');
        $interval = $request->query('interval');

        // Fetch the trades for the given symbol
        $trades = DB::table('coin_reports')
            ->where('symbol', $symbol)
            ->where('market', $market)
            ->where('interval', $interval)
            ->orderBy('id', 'ASC')
            ->get()
            ->map(function ($trade) {
                $trade->buyingCandle = json_decode($trade->buyingCandle);
                $trade->sellingCandle = json_decode($trade->sellingCandle);
                return $trade;
            });

        // Fetching Base Candle Data
        $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000, null, $market);

        foreach ($data as $index => &$candle) {

            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('Y-m-d H:i:s');
        }

        return view('CoinReports.coin-report-details', [
            'pageSlug' => 'Report Details',
            'symbol' => $symbol,
            'interval' => $interval,
            'trades' => $trades,
            'market' => $market,
            'data' => $data,
        ]);
    }

    public function showTrends($market, Request $request)
    {
        $trends = DB::table('market_trends')->where('market', $market)->where('interval', $request->interval)->get();
        $historicalTrends = MarketTrendService::getCurrentSupportResistanceGraph($request->symbol, $request->interval, $market, $request->candleSpan);
        // $historicalTrends = MarketTrendService::getCurrentSupportResistanceValue($request->symbol,$request->interval,'FUTURE',[5,10,15]);
        // dd($historicalTrends);

        return view('MarketTrends.index', ['trends' => $trends, 'pageSlug' => 'MarketTrends' . $market, 'historicalTrends' => $historicalTrends]);
    }
    public function getAvailableBalance(Request $request)
    {
        return BinanceApiService::fetchAvailableQuantity($request->symbol, auth()->user()->id, $request->market);
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
            $orders = DB::table('orders')->where('market', $market)->where('trade_acc', auth()->user()->id)
                ->where('side', 'BUY');

            if ($request->filled('start_date'))
                $orders = $orders->where('created_at', '>=', Carbon::parse($_GET['start_date'])->format('Y-m-d H:i:s'));
            if ($request->filled('end_date'))
                $orders = $orders->where('created_at', '<=', Carbon::parse($_GET['end_date'])->format('Y-m-d H:i:s'));
            if ($request->filled('symbol'))
                $orders = $orders->where('symbol', $_GET['symbol']);
            $orders = $orders->orderBy('created_at', 'desc')->get();
            // dd($orders);
            return view('live-trades.results', compact('orders', 'pageSlug'));
        } else if ($market === 'FUTURE') {



            $pageSlug = 'liveTradeResults' . $market;
            $orders = DB::table('live_trades_future_results')->where('trade_acc', auth()->user()->id)
                ->where('type', 'open');

            $orders = $orders->orderBy('created_at', 'desc')->get();


            $tradeStatistics = [
                'total_orders' => 0,
                'total_short' => 0,
                'total_long' => 0,
                'total_profit' => 0,
                'total_loss' => 0,
                'net_total' => 0,

            ];

            foreach ($orders as $order) {
                $tradeStatistics['total_orders'] += 1;
                $tradeStatistics['net_total'] += $order->currentProfit;

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
            return view('live-trades.results-future', compact('orders', 'tradeStatistics', 'pageSlug'));
        }
    }


    public function liveTradeCoins($market, Request $request)
    {
        if ($market === 'SPOT') {
            // $pageSlug = 'liveTradeResults' . $market;
            // $orders = DB::table('orders')->where('market', $market)->where('trade_acc', auth()->user()->id)
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

                ->where('tradeAccount', auth()->user()->id)
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
}
