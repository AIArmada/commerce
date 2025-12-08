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
        'events_table' => env('CART_EVENTS_TABLE', 'cart_events'),
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
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Multi-tenancy support for scoping carts by owner. When enabled, carts
    | are isolated per owner using the configured resolver. The resolver must
    | implement OwnerResolverInterface from commerce-support.
    |
    */
    'owner' => [
        'enabled' => env('CART_OWNER_ENABLED', false),
        'resolver' => env('CART_OWNER_RESOLVER', AIArmada\CommerceSupport\Contracts\NullOwnerResolver::class),
        'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', true),
    ],

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
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for cart operations. Limits are per identifier
    | (user ID, session ID, or IP address).
    |
    */
    'rate_limiting' => [
        'enabled' => env('CART_RATE_LIMITING_ENABLED', true),
        'limits' => [
            'add_item' => ['perMinute' => 60, 'perHour' => 500],
            'update_item' => ['perMinute' => 120, 'perHour' => 1000],
            'remove_item' => ['perMinute' => 60, 'perHour' => 500],
            'clear_cart' => ['perMinute' => 10, 'perHour' => 50],
            'checkout' => ['perMinute' => 5, 'perHour' => 20],
            'merge_cart' => ['perMinute' => 5, 'perHour' => 30],
            'get_cart' => ['perMinute' => 300, 'perHour' => 3000],
            'add_condition' => ['perMinute' => 30, 'perHour' => 200],
            'remove_condition' => ['perMinute' => 30, 'perHour' => 200],
            'default' => ['perMinute' => 60, 'perHour' => 500],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance optimizations for cart calculations. The lazy pipeline
    | enables memoized condition evaluation with 60-92% fewer computations.
    |
    */
    'performance' => [
        'lazy_pipeline' => env('CART_LAZY_PIPELINE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Sourcing
    |--------------------------------------------------------------------------
    |
    | Event sourcing configuration for cart audit trails and replay.
    | When enabled, cart events are persisted to the cart_events table.
    |
    */
    'event_sourcing' => [
        'enabled' => env('CART_EVENT_SOURCING_ENABLED', false),
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
    |
    | Multi-tier caching configuration for cart data. When enabled, carts are
    | cached using a read-through pattern with automatic cache invalidation.
    |
    */
    'cache' => [
        'enabled' => env('CART_CACHE_ENABLED', false),
        'store' => env('CART_CACHE_STORE', 'redis'),
        'prefix' => env('CART_CACHE_PREFIX', 'cart'),
        'ttl' => env('CART_CACHE_TTL', 3600), // 1 hour
        'queue' => env('CART_CACHE_QUEUE', 'default'),
    ],
];
