<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */
    'models' => [
        'billable' => env('CASHIER_MODEL', 'App\\Models\\User'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default' => env('CASHIER_GATEWAY', 'stripe'),
    'currency' => env('CASHIER_CURRENCY', 'MYR'),
    'locale' => env('CASHIER_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'stripe' => [
            'driver' => 'stripe',
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('CASHIER_CURRENCY', 'USD'),
            'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en_US'),
        ],

        'chip' => [
            'driver' => 'chip',
            'brand_id' => env('CHIP_BRAND_ID'),
            'api_key' => env('CHIP_API_KEY'),
            'webhook_key' => env('CHIP_WEBHOOK_KEY'),
            'currency' => env('CASHIER_CURRENCY', 'MYR'),
            'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'ms_MY'),
        ],
    ],
];
