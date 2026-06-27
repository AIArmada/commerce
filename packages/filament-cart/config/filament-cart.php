<?php

declare(strict_types=1);

use AIArmada\Cart\Services\BuiltInRulesFactory;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => 'cart_',
        'json_column_type' => env('FILAMENT_CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
        'tables' => [
            'snapshots' => 'cart_snapshots',
            'snapshot_items' => 'cart_snapshot_items',
            'snapshot_conditions' => 'cart_snapshot_conditions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'dynamic_rules_factory' => BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'E-Commerce',
        'sort' => 30,
    ],

    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'cart_items' => 31,
            'conditions' => 33,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'dashboard' => 1,
            'live_dashboard' => 5,
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
        'monitoring' => true,
        'global_conditions' => true,
        'abandonment_tracking' => true,
    ],

    'owner' => [
        'enabled' => env('FILAMENT_CART_OWNER_ENABLED', false),
        'include_global' => env('FILAMENT_CART_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('FILAMENT_CART_OWNER_AUTO_ASSIGN_ON_CREATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'stats_overview' => true,
        'abandoned_carts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Settings
    |--------------------------------------------------------------------------
    */
    'analytics' => [
        'high_value_threshold_minor' => 10000,
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

    /* Notifications */
    'notifications' => [
        'abandoned_cart' => [
            'enabled' => (bool) env('FILAMENT_CART_ABANDONED_NOTIFICATION_ENABLED', true),
            'from_address' => env('FILAMENT_CART_ABANDONED_FROM', 'info@unfairadvantage.my'),
            'from_name' => env('FILAMENT_CART_ABANDONED_FROM_NAME'),
            'brand_name' => env('FILAMENT_CART_ABANDONED_BRAND_NAME', 'Unfair Advantage'),
        ],
    ],
];
