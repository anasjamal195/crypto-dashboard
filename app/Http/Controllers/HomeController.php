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

        // $candles = BinanceApiService::getCandleStickData('BTCUSDT', '1s', 200, null, 'SPOT');
        // $sarValues = [];
        // $windowSize = 10;  // Define the size of your rolling window
        // $lowestSARs = [];  // This will store the lowest SAR values in the rolling window
        // $toleranceMargin = 0; // Tolerance margin as a percentage

        // foreach ($candles as $index => $candle) {
        //     $currentSAR = floatval($candle['sar']);

        //     if ($index == 0) {
        //         // Initialize the first SAR value
        //         $sarValues[$index] = 'initial';
        //         array_push($lowestSARs, $currentSAR);
        //     } else {
        //         // Calculate the current trend with tolerance
        //         if (count($lowestSARs) >= $windowSize) {
        //             // Remove the oldest value when the window size exceeds
        //             array_shift($lowestSARs);
        //         }
        //         $minSAR = min($lowestSARs); // Get the minimum SAR in the current window
        //         $allowedSAR = $minSAR * (1 + $toleranceMargin / 100); // Calculate allowed SAR with margin

        //         // Determine trend based on current SAR vs allowed SAR
        //         $sarValues[$index] = $currentSAR <= $allowedSAR ? 'down' : 'up';

        //         // Update the lowest SARs array
        //         if ($sarValues[$index] === 'down') {
        //             array_push($lowestSARs, $currentSAR);
        //         }
        //     }
        // }

        // dd($sarValues);


      
        $pageSlug = 'Dashboard';
        return view('welcome', compact('pageSlug'));
    }
}
