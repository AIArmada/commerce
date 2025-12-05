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
            'campaigns' => 5,
            'vouchers' => 10,
            'voucher_applications' => 20,
            'voucher_wallets' => 30,
            'gift_cards' => 50,
            'fraud_signals' => 60,
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
    'features' => [
        'campaigns' => true,
        'ab_testing' => true,
        'redemption_charts' => true,
        'gift_cards' => true,
        'fraud_detection' => true,
        'ai_optimization' => true,
        'stacking_config' => true,
        'targeting_config' => true,
    ],

    'order_resource' => null,
    'owners' => [],
    'default_currency' => 'MYR',
    'show_usage_stats' => true,
];
