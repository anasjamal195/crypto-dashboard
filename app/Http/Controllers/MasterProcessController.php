<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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



    public function handle($apiKey)
    {
        return $this->jsonResponse($apiKey, 'API key and IP validated successfully');
    }
  
}
