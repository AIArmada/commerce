<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),
    'reservations_table' => env('STOCK_RESERVATIONS_TABLE', 'stock_reservations'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'enabled' => env('STOCK_CART_INTEGRATION', true),
        'reservation_ttl' => env('STOCK_RESERVATION_TTL', 30), // Minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Integration
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'auto_deduct' => env('STOCK_AUTO_DEDUCT', true),
        'events' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'low_stock' => env('STOCK_EVENT_LOW_STOCK', true),
        'out_of_stock' => env('STOCK_EVENT_OUT_OF_STOCK', true),
        'reserved' => env('STOCK_EVENT_RESERVED', true),
        'released' => env('STOCK_EVENT_RELEASED', true),
        'deducted' => env('STOCK_EVENT_DEDUCTED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'keep_expired_for_minutes' => env('STOCK_KEEP_EXPIRED', 0),
    ],
];
