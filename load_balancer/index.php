<?php

header('Content-Type: application/json');
define('CACHE_FILE', __DIR__ . '/binance_weight_cache.json'); // Cache file path
define('CACHE_EXPIRATION', 60 - (time() % 60)); // Expiration time in seconds (1 minute)
define('REQUEST_THRESHOLD', 100); // Minimum Remaining Limit for weights
define('BINANCE_API_URL', 'https://fapi.binance.com/fapi/v1/klines'); // Binance API

// Function to get cached rate limit
function getRateLimit()
{
    if (!file_exists(CACHE_FILE)) {
        // Create cache file with default values if it doesn't exist
        file_put_contents(CACHE_FILE, json_encode(['used_weight' => 0, 'timestamp' => time()]));
        return 0;
    }

    $cacheData = json_decode(file_get_contents(CACHE_FILE), true);

    // If JSON is invalid or empty, reset the cache
    if (!$cacheData || !isset($cacheData['used_weight'], $cacheData['timestamp'])) {
        file_put_contents(CACHE_FILE, json_encode(['used_weight' => 0, 'timestamp' => time()]));
        return 0;
    }

    // Check if cache is expired
    if (time() - $cacheData['timestamp'] > CACHE_EXPIRATION) {
        file_put_contents(CACHE_FILE, json_encode(['used_weight' => 0, 'timestamp' => time()]));
        return 0;
    }

    return (int) $cacheData['used_weight'];
}

// Function to update rate limit cache
function updateRateLimit($newWeight)
{
    $cacheData = [
        'used_weight' => $newWeight,
        'timestamp' => time()
    ];
    file_put_contents(CACHE_FILE, json_encode($cacheData));
}

// Load API weight from cache
$usedWeight = getRateLimit();
$remainingWeight = 1200 - $usedWeight;
$requestData = $_POST;
if (!$requestData)
    $requestData = json_decode(file_get_contents('php://input'), true);


// Check if rate limit is exceeded
if ($remainingWeight < REQUEST_THRESHOLD) {


    // Build Binance API query
    // $params = [
    //     'symbol' => $requestData['symbol'],
    //     'interval' => $requestData['interval'],
    //     'limit' => $requestData['limit'],
    //     'balancerServerSequence' => $requestData['balancerServerSequence'],
    //     'nextServer' => intval($requestData['nextServer']) + 1,
    // ];

    // if (!empty($requestData['startTime'])) {
    //     $params['startTime'] = $requestData['startTime'];
    // }

    // $queryString = http_build_query($params);
    // $url = $params['balancerServerSequence'][$params['nextServer']];
    // // Initialize cURL request to Binance
    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, $url);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //     'Content-Type: application/json'
    // ]);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // curl_setopt($ch, CURLOPT_HEADER, true);
    // curl_setopt($ch, CURLOPT_POST, true); // Set request to POST
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params)); // Send JSON data

    // $response = curl_exec($ch);
    // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    // $headers = substr($response, 0, $headerSize);
    // $body = substr($response, $headerSize);

    // curl_close($ch);

    // // Return Binance response with headers
    // http_response_code($httpCode);
    // echo $body;
    // exit;


    echo null;
    exit;
}

// Read request data
if (!$requestData || !isset($requestData['symbol'], $requestData['interval'], $requestData['limit'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request parameters']);
    exit;
}

// Build Binance API query
$params = [
    'symbol' => $requestData['symbol'],
    'interval' => $requestData['interval'],
    'limit' => $requestData['limit']
];

if (!empty($requestData['startTime'])) {
    $params['startTime'] = $requestData['startTime'];
}

$queryString = http_build_query($params);
$url = BINANCE_API_URL . '?' . $queryString;

// Initialize cURL request to Binance
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

// Extract rate limit from Binance headers
preg_match('/x-mbx-used-weight-1m:\s*(\d+)/i', $headers, $matches);
if (!empty($matches[1])) {
    updateRateLimit((int) $matches[1]);
}

// Return Binance response with headers
http_response_code($httpCode);


echo $body;
exit;
