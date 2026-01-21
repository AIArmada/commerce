<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => 'cart_',
        'json_column_type' => env('FILAMENT_CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'snapshots' => 'cart_snapshots',
            'snapshot_items' => 'cart_snapshot_items',
            'snapshot_conditions' => 'cart_snapshot_conditions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'E-Commerce',

    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'recovery_campaigns' => 40,
            'recovery_templates' => 41,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'dashboard' => true,
        'analytics' => true,
        'recovery' => true,
        'monitoring' => true,
        'global_conditions' => true,
        'abandonment_tracking' => true,
    ],

    'owner' => [
        'enabled' => env('FILAMENT_CART_OWNER_ENABLED', false),
        'include_global' => env('FILAMENT_CART_OWNER_INCLUDE_GLOBAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'stats_overview' => true,
        'abandoned_carts' => true,
        'recovery_optimizer' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Settings
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'high_value_threshold_cents' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    */
    'synchronization' => [
        'queue_sync' => false,
        'queue_connection' => 'default',
        'queue_name' => 'cart-sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'abandonment_detection_minutes' => 30,
    ],

];
