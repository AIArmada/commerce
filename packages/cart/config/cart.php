<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table' => env('CART_DB_TABLE', 'carts'),
        'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
        'json_column_type' => env('CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'ttl' => env('CART_DB_TTL', 60 * 60 * 24 * 30), // 30 days, null to disable
        'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'storage' => env('CART_STORAGE_DRIVER', 'database'), // session, database, cache

    'money' => [
        'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
        'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'), // half_up, half_even, floor, ceil
    ],

    'tax' => [
        'default_rate' => env('CART_TAX_RATE', 0.0),
        'default_region' => env('CART_TAX_REGION'),
        'prices_include_tax' => env('CART_TAX_INCLUSIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavior
    |--------------------------------------------------------------------------
    */
    'empty_cart_behavior' => env('CART_EMPTY_BEHAVIOR', 'destroy'), // destroy, clear, preserve

    'migration' => [
        'auto_migrate_on_login' => env('CART_AUTO_MIGRATE', true),
        'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
    ],

    'events' => env('CART_EVENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_items' => env('CART_MAX_ITEMS', 1000),
        'max_item_quantity' => env('CART_MAX_QUANTITY', 10000),
        'max_data_size_bytes' => env('CART_MAX_DATA_BYTES', 1048576), // 1MB
        'max_string_length' => env('CART_MAX_STRING_LENGTH', 255),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Storage
    |--------------------------------------------------------------------------
    */
    'session' => [
        'key' => env('CART_SESSION_KEY', 'cart'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Storage
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'prefix' => env('CART_CACHE_PREFIX', 'cart'),
        'ttl' => env('CART_CACHE_TTL', 86400),
    ],
];
