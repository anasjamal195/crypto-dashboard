<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use App\Services\CoinReportService;
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
            ->groupBy('symbol')
            ->orderBy('total_entries', 'DESC')
            ->get();
        $pageSlug = 'Dashboard';
        return view('dashboard', compact('tradeData', 'pageSlug'));
    }
}
