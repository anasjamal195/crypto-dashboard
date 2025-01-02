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
}
