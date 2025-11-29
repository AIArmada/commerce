<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Vouchers & Discounts',

    'resources' => [
        'navigation_sort' => [
            'vouchers' => 10,
            'voucher_applications' => 20,
            'voucher_wallets' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    'tables' => [
        'date_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'order_resource' => null,
    'owners' => [],
    'default_currency' => 'MYR',
    'show_usage_stats' => true,
];
