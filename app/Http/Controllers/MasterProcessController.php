<?php

namespace App\Http\Controllers;

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
        $account_id = request('account');

        $validActions = ['FETCH_LIVE_TRADES_FUTURE'];

        // Prepare errors array
        $errors = [];

        if (!$action) {
            $errors['action'] = 'Action is required.';
        } elseif (!in_array($action, $validActions)) {
            $errors['action'] = 'Invalid action specified.';
        }

        if (!$account_id) {
            $errors['account'] = 'Account ID is required.';
        } elseif (intval($account_id) <= 0) {
            $errors['account'] = 'Account ID must be a positive integer.';
        }

        // If validation fails, return error JSON response
        if (!empty($errors)) {
            return $this->jsonResponse($errors, 'Validation failed', 422, false);
        }

        // Validation passed - process request
        switch ($action) {
            case 'FETCH_LIVE_TRADES_FUTURE':
                $data = $this->fetchLivetrades($account_id);
                return $this->jsonResponse($data, 'Live trades fetched successfully', 200, true);

            default:
                return $this->jsonResponse(null, 'Action not found', 404, false);
        }
    }





    // Custom functions for different action structure
    protected function fetchLivetrades($account)
    {
        $liveTrades = DB::table('live_trades_future_results')
            ->join('trade_orders', 'live_trades_future_results.orderId', '=', 'trade_orders.openOrderId')
            ->join('worker_symbols', 'live_trades_future_results.symbol', 'LIKE', 'worker_symbols.symbol')
            ->join('workers', 'worker_symbols.worker_id', 'LIKE', 'workers.worker_id')
            ->where('live_trades_future_results.trade_acc', $account)
            ->where('trade_orders.status', 'PENDING')
            ->where('live_trades_future_results.trade_status', 'open')
            ->select(
                'live_trades_future_results.*',
                'worker_symbols.worker_id',
                DB::raw('TIMESTAMPDIFF(SECOND, workers.updated_at, NOW()) as last_worker_update_seconds'),
                DB::raw('TIMESTAMPDIFF(SECOND, live_trades_future_results.updated_at, NOW()) as last_trade_update_seconds')
            )
            ->get()
            ->toArray();

        return $liveTrades;
    }
}
