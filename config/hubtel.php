<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hubtel Mode
    |--------------------------------------------------------------------------
    |
    | "sandbox" runs the whole payment flow (checkout initiation + webhook
    | confirmation) against a local mock implementation so the storefront can
    | be demoed and tested end-to-end without live Hubtel credentials. Set to
    | "live" once CY-Market supplies real Hubtel Receive Money / Checkout API
    | credentials (see TOR Sections 6.5, 10 and 16 - Assumptions).
    |
    */
    'mode' => env('HUBTEL_MODE', 'sandbox'),

    'base_url' => env('HUBTEL_BASE_URL', 'https://payproxyapi.hubtel.com'),

    'pos_sales_id' => env('HUBTEL_POS_SALES_ID'),
    'client_id' => env('HUBTEL_CLIENT_ID'),
    'client_secret' => env('HUBTEL_CLIENT_SECRET'),
    'merchant_account_number' => env('HUBTEL_MERCHANT_ACCOUNT_NUMBER'),

    // Hubtel calls this URL to confirm payment status changes (must be
    // publicly reachable in production - not exempt from CSRF, see
    // routes/api.php).
    'callback_url' => env('HUBTEL_CALLBACK_URL'),

    // Where the customer's browser is redirected back to after leaving to
    // approve payment on their phone.
    'return_url' => env('HUBTEL_RETURN_URL'),

    // Seconds to wait for the Hubtel API before treating the request as
    // failed (cart is preserved, customer shown a retry option - TOR §11).
    'timeout' => (int) env('HUBTEL_TIMEOUT', 30),
];
