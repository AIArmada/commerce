<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

$tablePrefix = env('STOCK_TABLE_PREFIX', 'stock_');

$transactionsTable = env('STOCK_TRANSACTIONS_TABLE', $tablePrefix . 'transactions');
$reservationsTable = env('STOCK_RESERVATIONS_TABLE', $tablePrefix . 'reservations');

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('STOCK_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'transactions' => $transactionsTable,
            'reservations' => $reservationsTable,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Register a resolver that returns the current owner (merchant, tenant, etc).
    | When enabled, stock transactions are automatically scoped to the owner.
    |
    */
    'owner' => [
        'enabled' => env('STOCK_OWNER_ENABLED', false),
        'resolver' => NullOwnerResolver::class,
        'include_global' => env('STOCK_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('STOCK_OWNER_AUTO_ASSIGN', true),
    ],

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
