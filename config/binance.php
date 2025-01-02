<?php

return [
    'api' => [
        'base_url' => 'https://api.binance.com/api/v3',
        'future_base_url' => 'https://fapi.binance.com/fapi/v1'
    ],
    'endpoints' => [
        'ticker_24hr' => '/ticker/24hr',
        'klines' => '/klines'
    ],
    'settings' => [
        'default_interval' => '15m',  // Default interval for data requests
        'default_limit' => 100        // Default limit for data fetches
    ]
];
