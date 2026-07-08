<?php

return [
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'mt5_bridge' => [
        'base_url' => env('MT5_BRIDGE_URL', 'http://mt5-bridge:9000'),
        'api_key' => env('MT5_BRIDGE_API_KEY'),
    ],

    'mail' => [
        'domain' => env('MAIL_DOMAIN'),
    ],
];
