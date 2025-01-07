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
    ],
    'cmcApi' =>[
        'apiKey'=> '2f721680-97ad-4ae4-8381-16518bd4d8ff', // anasdev5749@gmail.com's api key (Free 10,000 calls per month)
        'base_url' => '',
    ]
];
