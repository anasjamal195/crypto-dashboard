<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Jobs\TradeWorker\IdealTradeWorker;
use App\Services\BinanceApiService;
use App\Services\CoinReportService;
use App\Services\DynamicTradeService;
use App\Services\IdealTradeService;
use App\Services\LiveTradeService;
use App\Services\MarketTrendService;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

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
        // dd(MarketTrendService::getSymbolHistoricalTrends('BTCUSDT','1m','SPOT'));
        $pageSlug = 'Dashboard';

        $futureWallet = BinanceApiService::fetchFutureWalletDetails(2);
        $spotWallet = BinanceApiService::fetchSpotWalletDetails(2);
        return view('welcome', compact('pageSlug', 'spotWallet', 'futureWallet'));
    }
}
