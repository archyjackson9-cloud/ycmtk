<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Mode
    |--------------------------------------------------------------------------
    |
    | "log" writes every transactional SMS (TOR §6.6) to the "sms" log
    | channel instead of calling a real gateway, so the notification service
    | and every order-lifecycle trigger can be exercised with zero
    | credentials. Set to "live" once a Ghanaian SMS gateway is contracted.
    |
    */
    'mode' => env('SMS_MODE', 'log'),

    'base_url' => env('SMS_GATEWAY_BASE_URL'),
    'api_key' => env('SMS_GATEWAY_API_KEY'),
    'client_id' => env('SMS_GATEWAY_CLIENT_ID'),
    'sender_id' => env('SMS_GATEWAY_SENDER_ID', 'CYMarket'),

    // Retry attempts for failed sends (TOR §11 - "SMS delivery failure").
    'retry_attempts' => (int) env('SMS_RETRY_ATTEMPTS', 3),

    'timeout' => (int) env('SMS_TIMEOUT', 15),
];
