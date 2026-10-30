<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Mode
    |--------------------------------------------------------------------------
    |
    | "log" writes every SMS (order lifecycle TOR §6.6 and admin custom
    | messages) to the "sms" log channel instead of calling Arkesel, so the
    | whole flow can be exercised with zero credentials. Set to "live" (with
    | SMS_ARKESEL_API_KEY and an approved SMS_SENDER_ID) in production.
    |
    */
    'mode' => env('SMS_MODE', 'log'),

    // Arkesel SMS API v2 (https://developers.arkesel.com).
    'base_url' => env('SMS_ARKESEL_BASE_URL', 'https://sms.arkesel.com/api/v2'),
    'api_key' => env('SMS_ARKESEL_API_KEY'),

    // Alphanumeric sender name, max 11 characters. Must be registered and
    // approved in the Arkesel dashboard or messages are rejected.
    'sender_id' => substr((string) env('SMS_SENDER_ID', 'CYMarket'), 0, 11),

    // Queue attempts per SMS (the SendSms job backs off between attempts)
    // - TOR §11 "SMS delivery failure".
    'retry_attempts' => (int) env('SMS_RETRY_ATTEMPTS', 4),

    'timeout' => (int) env('SMS_TIMEOUT', 15),

    // Recipients per Arkesel request when broadcasting a custom message.
    'bulk_chunk_size' => (int) env('SMS_BULK_CHUNK_SIZE', 100),
];
