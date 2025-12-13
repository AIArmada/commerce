<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Billing',
    'navigation_badge_color' => 'success',

    'resources' => [
        'navigation_sort' => [
            'subscriptions' => 10,
            'customers' => 20,
            'invoices' => 30,
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
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'subscriptions' => true,
        'customers' => true,
        'invoices' => true,
        'payment_methods' => true,
        'dashboard_widgets' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'widgets' => [
            'mrr' => true,
            'active_subscribers' => true,
            'churn_rate' => true,
            'attention_required' => true,
            'revenue_chart' => true,
            'subscription_distribution' => true,
            'trial_conversions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),
];
