<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
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
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'abandoned_cart' => [
            'enabled' => (bool) env('FILAMENT_CART_ABANDONED_NOTIFICATION_ENABLED', true),
            'from_address' => env('FILAMENT_CART_ABANDONED_FROM', 'info@example.com'),
            'from_name' => env('FILAMENT_CART_ABANDONED_FROM_NAME'),
            'brand_name' => env('FILAMENT_CART_ABANDONED_BRAND_NAME', config('app.name')),
        ],
    ],
];
