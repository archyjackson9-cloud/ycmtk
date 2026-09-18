<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency & Locale (TOR §8.3 Branding & Localisation)
    |--------------------------------------------------------------------------
    */
    'currency' => env('CYMARKET_CURRENCY', 'GHS'),
    'currency_symbol' => 'GH₵',

    /*
    |--------------------------------------------------------------------------
    | Pilot / Delivery Defaults (TOR §3.1, §6.3, §11)
    |--------------------------------------------------------------------------
    |
    | These are first-run defaults only. Once the app is seeded, the fixed
    | delivery fee and pilot zone name are Super-Admin configurable from
    | Admin > Settings (the `settings` table) and take precedence over these
    | env-backed defaults - see App\Services\SettingsService.
    |
    */
    'default_delivery_fee' => (float) env('CYMARKET_DEFAULT_DELIVERY_FEE', 15.00),
    'pilot_zone_name' => env('CYMARKET_PILOT_ZONE_NAME', 'Tarkwa & Surrounding Communities'),

    /*
    | Default map centre for the delivery location picker (Leaflet /
    | OpenStreetMap) before a customer pins their own location - Tarkwa,
    | Western Region, Ghana.
    */
    'pilot_zone_lat' => (float) env('CYMARKET_PILOT_ZONE_LAT', 5.3006),
    'pilot_zone_lng' => (float) env('CYMARKET_PILOT_ZONE_LNG', -1.9878),

    /*
    |--------------------------------------------------------------------------
    | Stock & Cart Fallbacks (TOR §11 Fallbacks, Edge Cases & Error Handling)
    |--------------------------------------------------------------------------
    */

    // Soft stock reservation at "Payment Initiated" stage with a short
    // timeout (TOR §11 "Additional recommended safeguards").
    'stock_reservation_minutes' => (int) env('CYMARKET_STOCK_RESERVATION_MINUTES', 15),

    // "Customer abandons checkout after selecting delivery" - cart remains
    // available for a limited, configurable time.
    'abandoned_cart_hours' => (int) env('CYMARKET_ABANDONED_CART_HOURS', 48),

    // "Inventory Officer offline / delayed status update" - escalation
    // alert to Super Admin after a configurable time in Processing.
    'order_escalation_hours' => (int) env('CYMARKET_ORDER_ESCALATION_HOURS', 6),

    // Low-stock threshold fallback when a product does not define its own.
    'default_low_stock_threshold' => (int) env('CYMARKET_DEFAULT_LOW_STOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Admin panel
    |--------------------------------------------------------------------------
    */
    'filament_path' => env('FILAMENT_PATH', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Roles (TOR §4 Stakeholders & User Classes / §6.10 RBAC)
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'super_admin' => 'super-admin',
        'inventory_officer' => 'inventory-officer',
        'marketing_admin' => 'marketing-admin',
        'support_agent' => 'support-agent',
        'customer' => 'customer',
    ],
];
