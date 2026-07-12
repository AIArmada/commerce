<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),
        'tables' => (static function (): array {
            $prefix = env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_');

            return [
                'subscriptions' => $prefix . 'subscriptions',
                'subscription_items' => $prefix . 'subscription_items',
                'payment_methods' => $prefix . 'payment_methods',
            ];
        })(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'owner' => [
            'enabled' => env('CASHIER_CHIP_OWNER_ENABLED', false),
            'include_global' => env('CASHIER_CHIP_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('CASHIER_CHIP_OWNER_AUTO_ASSIGN_ON_CREATE', true),
            'validate_billable_owner' => env('CASHIER_CHIP_OWNER_VALIDATE_BILLABLE_OWNER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavior
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'charges_per_minute' => env('CASHIER_CHIP_CHARGES_PER_MINUTE', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
     */
    'path' => env('CASHIER_CHIP_PATH', 'chip'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
     */
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    'invoices' => [
        'renderer' => env('CASHIER_CHIP_INVOICE_RENDERER'),
        'paper' => env('CASHIER_CHIP_PAPER', 'A4'),
        'vendor_address' => env('CASHIER_CHIP_INVOICE_VENDOR_ADDRESS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
];
