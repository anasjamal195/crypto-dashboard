<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Models\OrderBookSnapshot;
use App\Services\BinanceApiService;
use App\Services\BinanceVolumeIndicatorsService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use App\Services\OrderBookStrategy;
use App\Services\ReportService\LongReportService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BinanceController extends Controller
{

    public function deleteCoinReport()
    {
        $formula = request('formula');
        if (!$formula)
            return redirect()->back()->withError('Error Deleting Report!');
        DB::table('coin_reports')->where('formula', $formula)->delete();
        DB::table('formula_details')->where('formula', $formula)->delete();
        return redirect()->back()->withSuccess('Report Deleted Successfully!');
    }
    public function volumeSignal()
    {
        $pageSlug = 'Volume Signal Dashboard';
        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 100);
        $volumeSignals = CommonHelpers::getVolumeSignals($symbol, $interval, true, null, $limit);



        $coinData = BinanceApiService::getCandleStickData($symbol, $interval, $limit, null, 'FUTURE', true);

        return view('volume-signals.index', compact('volumeSignals', 'symbol', 'pageSlug', 'coinData'));
    }
    public function getCoinReport($market, Request $request)
    {


        // =======Testing========================== 
        // dd(array_slice(BinanceApiService::getCandleStickData('BTCUSDT', '5m', 1000, null, 'FUTURE', true), -10));
        dd(CommonHelpers::getVolumeSignals('BTCUSDT','5m',true)[0]);
        // ========================================
        // Fetch all unique symbols from the database
        $interval = $request->interval;
        $stopLoss = $request->input('stopLoss') ?? 1;

        if (!request('formula')) {
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
                'interval'           => $interval,
                'market'             => $market,
                'liquidatedSymbols'  => [],
                'liquidatedIntervals' => [],
                'liquidatedMarkets'  => [],
            ]);
        }
        $query = DB::table('coin_reports')
            ->select(
                'symbol',
                'formula',
                'position',
                DB::raw('COUNT(*) as total_entries'),                          // Total number of entries per symbol
                DB::raw('SUM(profit) as total_profit'),                        // Sum of profit per symbol
                DB::raw('AVG(profit) as average_profit'),                      // Average profit per symbol
                DB::raw('AVG(duration) as average_duration'),                  // Average duration per symbol
                DB::raw('SUM(duration) as total_duration'),                    // Total duration per symbol
                DB::raw('MAX(profit) as max_profit'),                          // Maximum profit per symbol
                DB::raw('MIN(profit) as min_profit'),                          // Minimum profit per symbol
                DB::raw('MAX(lowestPricePercentage) as max_lowestPrice'),       // Maximum of lowestPrice per symbol
                DB::raw('MIN(lowestPricePercentage) as min_lowestPrice'),       // Minimum of lowestPrice per symbol
                DB::raw('MAX(created_at) as last_updated')                     // Last updated timestamp
            )
            ->where('market', $market)
            ->where('interval', $interval);

        // Request based filter for position in tradeData query
        if ($request->filled('position')) {
            $query->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $query->where('formula', $request->formula);
        }
        $averageDurationQuery = clone $query;
        $averageDuration  = $averageDurationQuery->where('profit', '>', '0')->where('duration', '<=', '60')->average('duration');

        $nearbyTradesQuery = DB::table('coin_reports');
        if ($request->filled('position')) {
            $nearbyTradesQuery->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $nearbyTradesQuery->where('formula', $request->formula);
        }
        $maxNearbyTrades = $nearbyTradesQuery
            ->selectRaw("
                DATE_FORMAT(
                    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.timestamp')), '%Y-%m-%d %H:%i:%s'),
                    '%Y-%m-%d %H:%i:00'
                ) - INTERVAL (MINUTE(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(buyingCandle, '$.timestamp')), '%Y-%m-%d %H:%i:%s')) % 5) MINUTE AS time_interval,
                COUNT(*) as entry_count
            ")
            ->groupBy('time_interval')
            ->orderBy('entry_count', 'DESC')
            ->first();


        $tradeData = $query->groupBy('symbol', 'position', 'formula')
            ->orderBy('total_entries', 'DESC')
            ->orderBy('last_updated', 'DESC')
            ->get();

        $pageSlug = 'CoinReport' . $market;

        // Liquidated coins query with position filter if provided
        $liquidatedCoinsQuery = DB::table('coin_reports')
            ->select('symbol', 'interval', 'market')
            // ->distinct()
            ->whereRaw('liquidationPrice >= lowestPrice');

        if ($request->filled('position')) {
            $liquidatedCoinsQuery->where('position', $request->position);
        }
        if ($request->filled('formula')) {
            $liquidatedCoinsQuery->where('formula', $request->formula);
        }
        $liquidatedCoins = $liquidatedCoinsQuery->get();

        $tradesAbove1h = DB::table('coin_reports')
            ->select('symbol', 'interval', 'market', 'profit')
            // ->distinct()
            ->whereRaw('duration > 60');

        if ($request->filled('position')) {
            $tradesAbove1h->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $tradesAbove1h->where('formula', $request->formula);
        }
        $tradesAbove1h = $tradesAbove1h->count();
        // Stop losses query with position filter if provided
        $stopLossesQuery = DB::table('coin_reports')
            ->select('symbol', 'interval', 'market', 'profit')
            // ->distinct()
            ->whereRaw('profit < 0');

        if ($request->filled('position')) {
            $stopLossesQuery->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $stopLossesQuery->where('formula', $request->formula);
        }
        $stopLossesTrades = $stopLossesQuery->count();
        $stopLossesTotal = abs($stopLossesQuery->sum('profit'));


        // Total Profitable Trades
        $profitsQuery = DB::table('coin_reports')
            ->select('symbol', 'interval', 'market', 'profit')
            // ->distinct()
            ->whereRaw('profit > 0');


        if ($request->filled('position')) {
            $profitsQuery->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $profitsQuery->where('formula', $request->formula);
        }
        $profitsQuery = $profitsQuery->get();

        $profitableTrades = $profitsQuery->count();


        $profitsTotal = abs($profitsQuery->sum('profit'));

        // Extracting unique symbols, intervals, and markets
        $liquidatedSymbols = json_decode(json_encode($liquidatedCoins->pluck('symbol')->unique()), true);
        $liquidatedIntervals = json_decode(json_encode($liquidatedCoins->pluck('interval')->unique()), true);
        $liquidatedMarkets = json_decode(json_encode($liquidatedCoins->pluck('market')->unique()), true);
        // dd($profitableTrades,$profitsTotal,$request->position,$request->formula);

        // Timeline Data preperation
        $tradeArr = DB::table('coin_reports');

        if ($request->filled('interval')) {
            $tradeArr->where('interval', $request->interval);
        }
        if ($request->filled('position')) {
            $tradeArr->where('position', $request->position);
        }

        if ($request->filled('formula')) {
            $tradeArr->where('formula', $request->formula);
        }


        $tradeArr = json_decode(json_encode($tradeArr->get()), true);

        $timelineData = array_map(function ($trade) use ($stopLoss) {

            $trade['buyingCandle'] = json_decode($trade['buyingCandle'], true);
            $trade['sellingCandle'] = json_decode($trade['sellingCandle'], true);

            $color = '';
            if ($trade['lowestPricePercentage'] > $stopLoss) {
                $color = 'yellow';
            }
            return [
                'symbol' => $trade['symbol'] . '( ' . $trade['position'] . ' )',
                'startTime' => $trade['buyingCandle']['timestampReadable'],
                'endTime' => $trade['sellingCandle']['timestampReadable'],
                'color' => $color ? $color : ($trade['position'] === 'SHORT' ? 'red' : 'green'),
                'id' => $trade['id'],
            ];
        }, $tradeArr);

        return view('CoinReports.coin-report', [
            'tradeData'          => $tradeData,
            'profitableTrades'   => $profitableTrades,
            'profitsTotal'       => $profitsTotal,
            'timelineData'       => $timelineData,
            'tradesAbove1h'      => $tradesAbove1h,
            'maxNearbyTrades'    => $maxNearbyTrades,
            'averageDuration'    => $averageDuration,
            'stopLossesTotal'    => $stopLossesTotal,
            'stopLoss'           => $stopLoss,
            'stopLossesTrades'   => $stopLossesTrades,
            'pageSlug'           => $pageSlug,
            'interval'           => $interval,
            'market'             => $market,
            'liquidatedSymbols'  => $liquidatedSymbols,
            'liquidatedIntervals' => $liquidatedIntervals,
            'liquidatedMarkets'  => $liquidatedMarkets,
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
                return $trade;
            });

        // Fetching Base Candle Data
        $startTime = $trades->first()->buyingCandle->binance_timestamp;

        $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000, $startTime, $market);

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








        return view('MarketTrends.index', ['trends' => $trends, 'pageSlug' => 'MarketTrends' . $market, 'historicalTrends' => $historicalTrends['data'], 'volumeSignals' => $historicalTrends['volumeSignals'], 'totalProfit' => round($historicalTrends['totalProfit'], 2)]);
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
            $orders = DB::table('orders')->where('market', $market)->where('trade_acc', Auth::user()->id)
                ->where('side', 'BUY');

            if ($request->filled('start_date'))
                $orders = $orders->where('created_at', '>=', Carbon::parse($request('start_date'))->format('Y-m-d H:i:s'));
            if ($request->filled('end_date'))
                $orders = $orders->where('created_at', '<=', Carbon::parse($request->input('end_date'))->format('Y-m-d H:i:s'));
            if ($request->filled('symbol'))
                $orders = $orders->where('symbol', $request->input('symbol'));
            if ($request->filled('formula'))
                $orders = $orders->where('formula', 'LIKE', $request->input('formula'));
            $orders = $orders->orderBy('created_at', 'desc')->get();
            // dd($orders);
            return view('live-trades.results', compact('orders', 'pageSlug'));
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
}
