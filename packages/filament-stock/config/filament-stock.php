<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Stock Management',

    'resources' => [
        'navigation_sort' => [
            'stock_transactions' => 10,
            'stock_reservations' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '45s',

    'tables' => [
        'date_format' => 'Y-m-d H:i:s',
        'quantity_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'low_stock_threshold' => 10,
    'default_extension_minutes' => 30,
    'show_reserved_stock' => true,
];
