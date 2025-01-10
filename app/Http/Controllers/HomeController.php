<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Jobs\TradeWorker\IdealTradeWorker;
use App\Services\BinanceApiService;
use App\Services\CoinReportService;
use App\Services\IdealTradeService;
use App\Services\MarketTrendService;
use DateTime;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // $symbol = 'XRPUSDT';
        // $interval = '1m';
        // $market = 'SPOT';

        // dd(CoinReportService::getCoinReport($symbol, $interval, 1000, $market));
        $pageSlug = 'Dashboard';
        return view('welcome', compact('pageSlug'));
    }
}
