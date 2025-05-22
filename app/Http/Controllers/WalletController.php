<?php
// app/Http/Controllers/WalletController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BinanceService; // Assuming you have your Binance methods in a service

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $traderId = auth()->id(); // or however you get the trader ID
        
        try {
            // Fetch wallet data using your existing methods
            $futureWallet = BinanceService::fetchFutureWalletDetails($traderId);
            $spotWallet = BinanceService::fetchSpotWalletDetails($traderId);
            
            return view('dashboard.wallet', compact('futureWallet', 'spotWallet'));
            
        } catch (\Exception $e) {
            Log::error("Wallet fetch error for trader {$traderId}: " . $e->getMessage());
            
            return view('dashboard.wallet', [
                'futureWallet' => null,
                'spotWallet' => null,
                'error' => 'Unable to fetch wallet data. Please check your API credentials.'
            ]);
        }
    }
    
    public function refresh(Request $request)
    {
        $traderId = auth()->id();
        
        try {
            // Fetch fresh wallet data
            $futureWallet = BinanceService::fetchFutureWalletDetails($traderId);
            $spotWallet = BinanceService::fetchSpotWalletDetails($traderId);
            
            return response()->json([
                'success' => true,
                'message' => 'Wallet data refreshed successfully',
                'data' => [
                    'futures' => $futureWallet,
                    'spot' => $spotWallet
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Wallet refresh error for trader {$traderId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh wallet data: ' . $e->getMessage()
            ], 500);
        }
    }
}

// Routes to add to web.php
/*
Route::middleware(['auth'])->group(function () {
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/refresh', [WalletController::class, 'refresh'])->name('wallet.refresh');
});
*/

// app/Services/BinanceService.php (if you want to move your methods to a service)

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceService
{
    public static function fetchFutureWalletDetails($trader) 
    { 
        $user = User::find($trader); 
        $apiKey = $user->api_key; 
        $apiSecret = $user->api_secret; 
     
        // Base Futures API URL 
        $baseUrl = 'https://fapi.binance.com'; 
     
        $timestamp = round(microtime(true) * 1000); 
        $recvWindow = 5000; 
     
        // Build query string for authenticated requests 
        $queryString = http_build_query([ 
            'timestamp' => $timestamp, 
            'recvWindow' => $recvWindow 
        ]); 
     
        // Generate HMAC SHA256 signature 
        $signature = hash_hmac('sha256', $queryString, $apiSecret); 
        $queryString .= "&signature={$signature}"; 
     
        // Actual Binance Futures API URLs 
        $accountUrl = "{$baseUrl}/fapi/v2/account?{$queryString}"; 
        $positionsUrl = "{$baseUrl}/fapi/v2/positionRisk?{$queryString}"; 
     
        try {
            $client = Http::withHeaders([
                'X-MBX-APIKEY' => $apiKey 
            ]); 
         
            // Send both requests 
            $accountResponse = $client->get($accountUrl)->json(); 
            $positionsResponse = $client->get($positionsUrl)->json(); 
         
            // Check for successful response and return structured data 
            if (isset($accountResponse['totalWalletBalance'])) { 
                return [ 
                    'wallet_balance' => floatval($accountResponse['totalWalletBalance']), 
                    'unrealized_profit' => floatval($accountResponse['totalUnrealizedProfit']), 
                    'margin_balance' => floatval($accountResponse['totalMarginBalance']), 
                    'available_balance' => floatval($accountResponse['availableBalance']), 
                    'positions' => collect($positionsResponse)->filter(function ($pos) { 
                        return abs(floatval($pos['positionAmt'])) > 0; 
                    })->values() 
                ]; 
            } 
         
            // Log and handle failed fetch 
            Log::error("FUTURE Wallet Error for trader {$trader}: " . json_encode($accountResponse)); 
            return null;
            
        } catch (\Exception $e) {
            Log::error("FUTURE Wallet Exception for trader {$trader}: " . $e->getMessage());
            return null;
        }
    } 
     
    public static function fetchSpotWalletDetails($trader) 
    { 
        $user = User::find($trader); 
        $apiKey = $user->api_key; 
        $apiSecret = $user->api_secret; 
        $baseUrl = config('binance.api.base_url', 'https://api.binance.com'); 
     
        $timestamp = round(microtime(true) * 1000); 
        $recvWindow = 5000; 
     
        $queryString = http_build_query([ 
            'timestamp' => $timestamp, 
            'recvWindow' => $recvWindow 
        ]); 
        $signature = hash_hmac('sha256', $queryString, $