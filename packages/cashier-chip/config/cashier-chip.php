<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | CHIP API Configuration
    |--------------------------------------------------------------------------
    |
    | These settings are inherited from the chip package configuration.
    | You can override them here if needed.
    |
    */

    'api_key' => env('CHIP_COLLECT_API_KEY'),
    'brand_id' => env('CHIP_COLLECT_BRAND_ID'),
    'environment' => env('CHIP_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where Cashier's views, such as the payment
    | verification screen, will be available from. You're free to tweak
    | this path according to your preferences and application design.
    |
    */

    'path' => env('CASHIER_CHIP_PATH', 'chip'),

    /*
    |--------------------------------------------------------------------------
    | CHIP Webhooks
    |--------------------------------------------------------------------------
    |
    | Your CHIP webhook public key is used to verify incoming webhooks.
    | The tolerance setting will check the timestamp drift between
    | the current time and the signed request's timestamp.
    |
    */

    'webhook' => [
        'public_key' => env('CHIP_COMPANY_PUBLIC_KEY'),
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
        'tolerance' => env('CHIP_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. CHIP primarily supports MYR (Malaysian Ringgit).
    |
    */

    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default ms_MY locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */

    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),

    /*
    |--------------------------------------------------------------------------
    | Payment Confirmation Notification
    |--------------------------------------------------------------------------
    |
    | If this setting is enabled, Cashier will automatically notify customers
    | whose payments require additional verification. You should listen to
    | CHIP's webhooks in order for this feature to function correctly.
    |
    */

    'payment_notification' => env('CASHIER_CHIP_PAYMENT_NOTIFICATION'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    |
    | The following options determine how Cashier invoices are rendered.
    | CHIP provides its own invoice URLs, but you can customize the
    | local invoice rendering if needed.
    |
    */

    'invoices' => [
        'renderer' => env('CASHIER_CHIP_INVOICE_RENDERER'),

        'options' => [
            'paper' => env('CASHIER_CHIP_PAPER', 'A4'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cashier Logger
    |--------------------------------------------------------------------------
    |
    | This setting defines which logging channel will be used by Cashier
    | to write log messages. You are free to specify any of your
    | logging channels listed inside the "logging" configuration file.
    |
    */

    'logger' => env('CASHIER_CHIP_LOGGER'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Settings
    |--------------------------------------------------------------------------
    |
    | Configure how subscriptions are handled. CHIP uses recurring tokens
    | for subscription billing rather than native subscription objects.
    |
    */

    'subscriptions' => [
        // Days to wait before retrying a failed payment
        'payment_retry_days' => env('CASHIER_CHIP_PAYMENT_RETRY_DAYS', 3),

        // Maximum number of payment retry attempts
        'max_payment_retries' => env('CASHIER_CHIP_MAX_PAYMENT_RETRIES', 3),

        // Grace period in days after subscription ends
        'grace_period_days' => env('CASHIER_CHIP_GRACE_PERIOD_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for database table prefixes and naming
    |
    */

    'database' => [
        'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),
    ],
];
