<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MTN Mobile Money (MoMo Collection API - "Request to Pay")
    |--------------------------------------------------------------------------
    |
    | "simulate" runs the whole payment flow against a local mock page so the
    | storefront can be demoed/tested without any MTN credentials. "live"
    | calls the real MTN MoMo Collection API: the customer gets a prompt on
    | their phone and approves it with their MoMo PIN (no redirect).
    |
    | MOMO_ENVIRONMENT is the X-Target-Environment header: "sandbox" for MTN's
    | developer sandbox (amounts must be in EUR there), or "mtnghana" for
    | production Ghana (GHS). Credentials come from momodeveloper.mtn.com
    | (sandbox) or your MTN Ghana MoMo business onboarding (production).
    |
    */
    'mode' => env('MOMO_MODE', 'simulate'),

    'environment' => env('MOMO_ENVIRONMENT', 'sandbox'),

    'base_url' => env('MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),

    // Ocp-Apim-Subscription-Key for the Collection product.
    'subscription_key' => env('MOMO_SUBSCRIPTION_KEY'),

    // API user (UUID) + API key, used to fetch OAuth tokens.
    'api_user' => env('MOMO_API_USER'),
    'api_key' => env('MOMO_API_KEY'),

    // ISO currency sent to MTN. Sandbox only accepts EUR; Ghana is GHS.
    'currency' => env('MOMO_CURRENCY', 'GHS'),

    // MTN calls this URL with the final status. Must be public https in
    // production. Never trusted on its own - we re-query MTN for the status.
    'callback_url' => env('MOMO_CALLBACK_URL'),

    'timeout' => (int) env('MOMO_TIMEOUT', 30),

    // Pending requests older than this are considered abandoned by the
    // reconciliation job (MTN prompts expire after a few minutes).
    'pending_expiry_minutes' => (int) env('MOMO_PENDING_EXPIRY_MINUTES', 10),
];
