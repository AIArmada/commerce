<?php

declare(strict_types=1);

return [
    'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),
        'json_column_type' => env('CASHIER_CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => (static function (): array {
            $prefix = env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_');

            return [
                'subscriptions' => $prefix . 'subscriptions',
                'subscription_items' => $prefix . 'subscription_items',
            ];
        })(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'path' => env('CASHIER_CHIP_PATH', 'chip'),
    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    */
    'subscriptions' => [
        'retry_days' => env('CASHIER_CHIP_RETRY_DAYS', 3),
        'max_retries' => env('CASHIER_CHIP_MAX_RETRIES', 3),
        'grace_days' => env('CASHIER_CHIP_GRACE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
        'tolerance' => env('CHIP_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    'invoices' => [
        'renderer' => env('CASHIER_CHIP_INVOICE_RENDERER'),
        'paper' => env('CASHIER_CHIP_PAPER', 'A4'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logger' => env('CASHIER_CHIP_LOGGER'),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'payment_notification' => env('CASHIER_CHIP_PAYMENT_NOTIFICATION'),
];
