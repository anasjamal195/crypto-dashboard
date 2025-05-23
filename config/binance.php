<?php

return [
    'bot' => [
        'api_key' => 'fc621b21-00be-4c9e-899d-dccca11462b6',
        'base_url' => 'https://rocket.cryptoapis.store',
        'endpoints' => [
            'check_open_order' => '/check-open-order'
        ],

    ],
    'api' => [
        'base_url' => 'https://api.binance.com/api/v3',
        'future_base_url' => 'https://fapi.binance.com/fapi/v1'
    ],
    'endpoints' => [
        'ticker_24hr' => '/ticker/24hr',
        'klines' => '/klines',
        'ticker_price' => '/ticker/price',
        'exchange_info' => '/exchangeInfo',
        'margin' => '/margin',
        'server_time' => '/time',
        'order' => '/order',
        'depth' => '/depth',
        'leverage' => '/leverage',
        'account_info' => '/account',

    ],
    'settings' => [
        'default_interval' => '15m',  // Default interval for data requests
        'default_limit' => 100        // Default limit for data fetches
    ],
    'cmcApi' => [
        'api_key' => 'e942c633-e3be-4a5d-8b9c-42d389b3511b', // anasdev355@gmail.com's api key (Free 10,000 calls per month)
        'base_url' => 'https://pro-api.coinmarketcap.com/v1',
        'trending_coins' => '/cryptocurrency/trending/latest',
        'latest_coins' => '/cryptocurrency/listings/latest',
        'info' => '/cryptocurrency/info'

    ],



    // Master Process manager
    'process_manager_client_key' => env('PROCESS_MANAGER_CLIENT_KEY', '1b88399e-2ec0-4a34-bd49-ff50f9adc013'),
    'process_manager_server_ip' => env('PROCESS_MANAGER_SERVER_IP', '170.64.211.163'),
];
