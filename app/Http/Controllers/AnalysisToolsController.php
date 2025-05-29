<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Services\BinanceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;

class AnalysisToolsController extends Controller
{
    public function orderBookTool()
    {

        $pageSlug = 'orderBookTool';





        return view('analysis-tools.order-book-tool', compact('pageSlug'));
    }


    public function volumeTool()
    {

        $pageSlug = 'volumeTool';
        $symbol = request('symbol', 'BTCUSDT');
        $interval = request('interval', '5m');
        $limit = request('limit', 200);

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





        return view('analysis-tools.bollinger-band-tool', compact('pageSlug'));
    }


    public function technicalTrendTool()
    {

        $pageSlug = 'technicalTrendTool';





        return view('analysis-tools.technical-trend-tool', compact('pageSlug'));
    }


    public function chartPatternTool()
    {

        $pageSlug = 'chartPatternTool';





        return view('analysis-tools.chart-pattern-tool', compact('pageSlug'));
    }

    public function indicatorComparisonTool()
    {

        $pageSlug = 'indicatorComparisonTool';





        return view('analysis-tools.indicator-comparison-tool', compact('pageSlug'));
    }
}
