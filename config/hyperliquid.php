<?php

return [

    'sdk_api' => [
        'base_url' => 'http://209.38.95.33:5000',
    ],
    'sdk_endpoints' => [
        'open_position' => '/open-position',
        'close_position' => '/close-position',
        'update_tp_sl' => '/update-tp-sl',
        'cancel_orders' => '/cancel-orders',
        'position_details' => '/position-details',
        'last_filled_order' => '/last-filled-order',
    ],


    // Master Process manager
    'process_manager_client_key' => env('PROCESS_MANAGER_CLIENT_KEY', '1b88399e-2ec0-4a34-bd49-ff50f9adc013'),
    'process_manager_server_ip' => env('PROCESS_MANAGER_SERVER_IP', '170.64.211.163'),
    'master_server_url' => 'https://egeniuscare.shop/'
];
