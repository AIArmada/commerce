<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default storage driver that will be used
    | for storing cart data. Supported drivers: "session", "database", "cache"
    |
    */
    'storage' => env('CART_STORAGE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for session-based storage
    |
    */
    'session' => [
        'key' => env('CART_SESSION_KEY', 'cart'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database-based storage
    |
    */
    'database' => [
        'table' => env('CART_DB_TABLE', 'carts'),

        // Use SELECT ... FOR UPDATE pessimistic locking in addition to optimistic locking (CAS).
        // Default: false (optimistic locking only via version numbers is sufficient for most cases)
        //
        // When false: Uses Compare-And-Swap (CAS) with version numbers for conflict detection.
        //            Allows higher concurrency but may require retries on conflicts.
        //            Best for: Most applications, high-traffic scenarios, cloud databases.
        //
        // When true: Adds SELECT ... FOR UPDATE to prevent concurrent modifications.
        //           Provides stronger guarantees but reduces concurrency and may cause deadlocks.
        //           Enable when: Multiple servers modify the same cart simultaneously AND
        //                        you cannot tolerate any CAS conflicts/retries.
        'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cache-based storage
    |
    */
    'cache' => [
        'prefix' => env('CART_CACHE_PREFIX', 'cart'),
        'ttl' => env('CART_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Money & Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Laravel Money is used internally for all calculations.
    | Only currency configuration is needed - Laravel Money handles precision.
    |
    */
    'money' => [
        // Default currency for all Money objects
        'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable cart events
    |
    */
    'events' => env('CART_EVENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cart Migration Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for guest-to-user cart migration
    |
    */
    'migration' => [
        // Automatically migrate guest cart to user cart on login
        'auto_migrate_on_login' => env('CART_AUTO_MIGRATE_ON_LOGIN', true),

        // Strategy for handling conflicts when merging carts
        // Options: 'add_quantities', 'keep_highest_quantity', 'keep_user_cart', 'replace_with_guest'
        'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Limits & Security
    |--------------------------------------------------------------------------
    |
    | Limits to prevent DoS attacks and ensure reasonable data sizes
    |
    */
    'limits' => [
        // Maximum number of items in a cart
        'max_items' => env('CART_MAX_ITEMS', 1000),

        // Maximum size of cart data in bytes (items, conditions, metadata)
        'max_data_size_bytes' => env('CART_MAX_DATA_SIZE_BYTES', 1024 * 1024), // 1MB

        // Maximum quantity per item
        'max_item_quantity' => env('CART_MAX_ITEM_QUANTITY', 10000),

        // Maximum string length for item names/attributes
        'max_string_length' => env('CART_MAX_STRING_LENGTH', 255),
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty Cart Behavior
    |--------------------------------------------------------------------------
    |
    | Control what happens when a cart becomes empty.
    |
    */
    'preserve_empty_cart' => env('CART_PRESERVE_EMPTY', false),
];
