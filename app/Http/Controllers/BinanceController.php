<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BinanceController extends Controller
{
    public function getCoinReportDetails(Request $request)
    {
        // Get the symbol from the request
        $symbol = $request->query('symbol');
        $interval = $request->query('interval');

        // Fetch the trades for the given symbol
        $trades = DB::table('coin_reports')
            ->where('symbol', $symbol)
            ->orderBy('id', 'ASC')
            ->get()
            ->map(function ($trade) {
                $trade->buyingCandle = json_decode($trade->buyingCandle);
                $trade->sellingCandle = json_decode($trade->sellingCandle);
                return $trade;
            });

        // Fetching Base Candle Data
        $data = BinanceApiService::getCandleStickData($symbol, $interval, 1000);

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
            'data' => $data,
        ]);
    }

    public function showTrends()
    {
        $trends = DB::table('market_trends')->get();
        return view('MarketTrends.index', ['trends' => $trends, 'pageSlug' => 'MarketTrends']);
    }
    public function showAverages($market)
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
            ->where('market',$market)
            ->groupBy('symbol', 'interval') // Include 'market' in the group by clause
            ->get();

        return view('IdealIndicators.index', ['averages' => $averages, 'pageSlug' => 'averageCandlesticks']);
    }
}
