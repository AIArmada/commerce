<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Shipping',

    'resources' => [
        'navigation_sort' => [
            'orders' => 10,
            'pickups' => 20,
            'tracking' => 30,
            'tracking_events' => 40,
            'webhook_logs' => 50,
        ],
    ],

    'navigation_badge_color' => 'primary',

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    'tables' => [
        'date_format' => 'Y-m-d H:i:s',
    ],
];
