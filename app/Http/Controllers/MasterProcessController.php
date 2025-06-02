<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Models\User;
use App\Services\BinanceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterProcessController extends Controller
{

    public function __construct(Request $request)
    {
        $expectedApiKey = config('binance.process_manager_client_key');
        $expectedServerIp = config('binance.process_manager_server_ip');

        $providedApiKey = $request->header('api_key') ?? $request->query('api_key');

        // Get the real client IP address, considering proxies
        $clientIp = $request->ip();

        if ($providedApiKey !== $expectedApiKey) {
            return $this->jsonResponse(null, 'Unauthorized: Invalid client Key.', 401, false);
        }

        if ($clientIp !== $expectedServerIp) {

            return $this->jsonResponse(null, 'Unauthorized: Invalid client IP address.', 401, false);
        }
    }
    protected function jsonResponse($data = null,  $message = null, int $statusCode = 200, bool $success = true)
    {
        // Get server IP address
        $serverIp = $_SERVER['SERVER_ADDR'] ?? request()->server('SERVER_ADDR') ?? null;

        // Get domain name (host)
        $domain = request()->getHost();

        return response()->json([
            'success'   => $success,
            'message'   => $message,
            'data'      => $data,
            'server_ip' => $serverIp,
            'domain'    => $domain,
        ], $statusCode);
    }


    // Handle Incoming Validated Requests


    public function handleRequest()
    {
        // Validate inputs
        $action = request('action');
        $email = request('email');

        $validActions = [
            'FETCH_LIVE_TRADES_FUTURE',
            'CLOSE_LIVE_TRADE',
            'SYNC_USERS',
        ];

        // Prepare errors array
        $errors = [];

        if (!$action) {
            $errors['action'] = 'Action is required.';
        } elseif (!in_array($action, $validActions)) {
            $errors['action'] = 'Invalid action specified.';
        }

        // if (!$email) {
        //     $errors['email'] = 'Email is required.';
        // } elseif (intval($email) <= 0) {
        //     $errors['email'] = 'Email must be a positive integer.';
        // }

        // If validation fails, return error JSON response
        if (!empty($errors)) {
            return $this->jsonResponse($errors, 'Validation failed', 422, false);
        }

        // Validation passed - process request
        switch ($action) {
            case 'FETCH_LIVE_TRADES_FUTURE':
                $data = $this->fetchLivetrades($email);
                return $this->jsonResponse($data, 'Live trades fetched successfully', 200, true);
            case 'CLOSE_LIVE_TRADE':
                $data = $this->closeLivetrades($email);
                return $this->jsonResponse($data, 'Live trades closed successfully', 200, true);
            case 'SYNC_USERS':
                $data = $this->syncUsers();
                return $this->jsonResponse($data, 'Live trades closed successfully', 200, true);

            default:
                return $this->jsonResponse(null, 'Action not found', 404, false);
        }
    }





    // Custom functions for different action structure
    protected function fetchLivetrades($email)
    {
        $trade_acc = User::where('email', $email)->first()->id;
        $liveTrades = DB::table('live_trades_future_results')
            ->join('trade_orders', 'live_trades_future_results.orderId', '=', 'trade_orders.openOrderId')
            ->join('worker_symbols', 'live_trades_future_results.symbol', 'LIKE', 'worker_symbols.symbol')
            ->join('workers', 'worker_symbols.worker_id', 'LIKE', 'workers.worker_id')
            ->join('users', 'live_trades_future_results.trade_acc', '=', 'users.id')
            ->where('live_trades_future_results.trade_acc', $trade_acc)
            ->where('trade_orders.status', 'PENDING')
            ->where('live_trades_future_results.trade_status', 'open')
            ->select(
                'live_trades_future_results.*',
                'worker_symbols.worker_id',
                'users.email as user_email',
                'trade_orders.tp_order_id',
                'trade_orders.sl_order_id',
                'trade_orders.status',
                DB::raw("TIMESTAMPDIFF(
                    SECOND,
                    live_trades_future_results.updated_at,
                    
                    CONVERT_TZ(NOW(), '+00:00', '+05:00')
                    ) as last_trade_update_seconds"),

                DB::raw("TIMESTAMPDIFF(
                    SECOND,
                    workers.updated_at,
                    CONVERT_TZ(NOW(), '+00:00', '+05:00')
                    ) as last_worker_update_seconds")
            )
            ->get()
            ->toArray();

        return $liveTrades;
    }

    protected function closeLiveTrades($email)
    {
        // Safely Closing Live trades that are active
        $openOrderId = request('openOrderId');
        BinanceApiService::closeMarketPositionLiveTrader($openOrderId);
        return true;
    }


    protected function syncUsers()
    {
        $users = User::where('is_active', true)->where('role', 'trader')->get();

        return $users;
    }


    public function handleExternalCandleStickRequest(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'interval' => 'required|string',
            'limit' => 'required|integer',
            'startTime' => 'nullable|string',
            'market' => 'required|string|in:SPOT,FUTURE',
            'processed' => 'nullable|string',
        ]);

        // Optionally, parse 'processed' as a boolean
        $processed = filter_var($validated['processed'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return response()->json(BinanceApiService::getCandleStickDataCached(
            $validated['symbol'],
            $validated['interval'],
            $validated['limit'],
            $validated['startTime'],
            $validated['market'],
            $processed,

        ));
    }


    public function syncDomain()
    {
        $domain = request('domain_name');
        try {
            $count = CommonHelpers::syncExternalUsers($domain);
            return redirect()->back()->withSuccess('Domain synced successfully. Updated ' . $count . ' users...');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors('Failed to sync domain: ' . $e->getMessage());
        }
    }


}
