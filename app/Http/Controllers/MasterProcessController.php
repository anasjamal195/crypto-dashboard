<?php

namespace App\Http\Controllers;

use App\CommonHelpers;
use App\Jobs\ThreadsOrderBook\TriggersThread;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\HyperLiquidApiService;
use App\Services\MailerService;
use App\Services\SupervisorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
            'RESTART_WORKER',
            'RESTART_MULTITHREAD',
            'CHECK_WORKER_STATUS',
            'SEND_EMAIL',
            'FETCH_MISSING_TRADES',

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
            case 'RESTART_WORKER':
                $data = $this->restartWorker();
                return $this->jsonResponse($data, 'Worker Restarted successfully', 200, true);
            case 'RESTART_MULTITHREAD':
                $data = $this->restartMultithread();
                return $this->jsonResponse($data, 'Multithread Restarted successfully', 200, true);
            case 'CHECK_WORKER_STATUS':
                $data = $this->checkWorkerStatus();
                return $this->jsonResponse($data, 'Worker Status sent successfully', 200, true);
            case 'SEND_EMAIL':
                $data = $this->sendEmail();
                return $this->jsonResponse($data, 'Email Sent', 200, true);
            case 'FETCH_MISSING_TRADES':
                $data = $this->fetchMissingTrades($email);
                return $this->jsonResponse($data, 'Missing Trades Fetched', 200, true);

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

    protected function fetchMissingTrades($email)
    {
        $trade_acc = User::where('email', $email)->first()->id;

        $startTime = request('start_time'); // e.g. 2025-08-20 22:30:00 (UTC)
        $endTime = request('end_time');
        $symbol = request('symbol');

        $liveTrades = DB::table('live_trades_future_results')
            ->where('trade_acc', $trade_acc)
            ->where('symbol', $symbol)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->get();
        return $liveTrades;
    }

    protected function sendEmail()
    {

        $details = request('details');
        MailerService::sendFutureTradeDynamicEmail($details, true);
    }
    protected function closeLiveTrades($email)
    {
        // Safely Closing Live trades that are active
        $openOrderId = request('openOrderId');
        $openOrder = DB::table('live_trades_future_results')->where('orderId', $openOrderId)->first();

        $openOrder->exchange === 'binance' ?
            BinanceApiService::closeMarketPositionLiveTrader($openOrderId)
            : HyperLiquidApiService::closeMarketPositionLiveTrader($openOrderId);
        return true;
    }


    protected function restartWorker()
    {
        // Safely Closing Live trades that are active
        $workerId = request('workerId');
        $email = request('email');
        $openOrderId = request('openOrderId');

        $userId = User::where('email', $email)->first()->id;
        TriggersThread::dispatch($workerId, $userId, $openOrderId);
        return true;
    }



    protected function checkWorkerStatus()
    {
        // Safely Closing Live trades that are active
        $activeWorkers = DB::table('workers')->where('active_status')->get();
        return $activeWorkers;
    }
    protected function restartMultithread()
    {
        try {
            // Cleanup
            DB::statement('UPDATE trade_handler SET isWorkerDispatched = 0');
            DB::statement('UPDATE workers SET symbol_count = 0');
            DB::statement('UPDATE workers SET trade_status = 0');
            DB::statement('DELETE FROM worker_symbols WHERE 1');
            Artisan::call('queue:flush');
            Artisan::call('queue:clear');
            SupervisorService::executeCommand('killall -9 php');
            DB::table('jobs')->truncate();

            $threads = DB::table('workers')->where('active_status', 1)->pluck('worker_id');

            // Prepare Processes for start sequence
            // Start Sequence
            $processes = [
                // 'laravel_saftey_worker',
                'laravel_future_coin_dumper',
                // 'laravel_order_book_signals_worker',
            ];

            foreach ($threads as $index => $thread) {
                $processes[] = 'laravel_thread_workers:laravel_thread_workers_' . sprintf("%02d", $index);
            }
            $processes[] = 'acc_2_order_book_long_worker';

            // ===================================



            foreach ($processes as $process)
                SupervisorService::restart($process);

            // Dispatch All threads
            Artisan::call('queue:flush');
            foreach ($threads as $workerId) {
                TriggersThread::dispatch($workerId, auth()->user()->id, null);
            }
            return redirect()->back()->withSuccess('Action ' . 'Multithread Started');
        } catch (\Throwable $th) {
            return redirect()->back()->withError('Failed to Perform Multithread Restart ');
        }

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
    public function handleExternalCandleStickRequestHyperliquid(Request $request)
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

        return response()->json(HyperLiquidApiService::getCandleStickDataCached(
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
