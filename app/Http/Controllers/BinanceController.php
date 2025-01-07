<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BinanceController extends Controller
{
    public function getCoinReport($market, Request $request)
    {
        // Fetch all unique symbols from the database
        $tradeData = DB::table('coin_reports')
            ->select(
                'symbol',
                DB::raw('COUNT(*) as total_entries'),                          // Total number of entries per symbol
                DB::raw('SUM(profit) as total_profit'),                        // Sum of profit per symbol
                DB::raw('AVG(profit) as average_profit'),                      // Average profit per symbol
                DB::raw('AVG(duration) as average_duration'),                  // Average duration per symbol
                DB::raw('MAX(profit) as max_profit'),                          // Maximum profit per symbol
                DB::raw('MIN(profit) as min_profit'),                          // Minimum profit per symbol
                DB::raw('MAX(lowestPricePercentage) as max_lowestPrice'),                // Maximum of lowestPrice per symbol
                DB::raw('MIN(lowestPricePercentage) as min_lowestPrice'),                 // Minimum of lowestPrice per symbol
                DB::raw('MAX(created_at) as last_updated'),                 // Minimum of lowestPrice per symbol
            )
            ->where('market', $market)
            ->groupBy('symbol')
            ->orderBy('total_entries', 'DESC')
            ->get();
        $pageSlug = 'CoinReport' . $market;
        return view('CoinReports.coin-report', compact('tradeData', 'pageSlug','market'));
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
        $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000,null,$market);

        foreach ($data as $index => &$candle) {

            $candle['timestamp'] = $candle['timestamp'] / 1000;
            $date = new \DateTime("@{$candle['timestamp']}");
            $date->setTimezone(new \DateTimeZone('Asia/Karachi'));
            $candle['timestamp'] =  $date->format('d-m-Y H:i:s');
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
        return view('MarketTrends.index', ['trends' => $trends, 'pageSlug' => 'MarketTrends' . $market]);
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
}
