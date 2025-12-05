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

    /*
    |--------------------------------------------------------------------------
    | Cart Integration (aiarmada/cart)
    |--------------------------------------------------------------------------
    |
    | Configure tight integration with the Cart package when installed.
    |
    */
    'cart' => [
        // Enable cart integration
        'enabled' => env('CASHIER_CART_ENABLED', true),

        // Register checkout macro on cart manager
        'register_checkout_macro' => env('CASHIER_CART_CHECKOUT_MACRO', true),

        // Metadata key for storing cart ID in payment
        'metadata_key' => env('CASHIER_CART_METADATA_KEY', 'cart_id'),

        // Metadata key for storing order ID
        'order_id_key' => env('CASHIER_CART_ORDER_ID_KEY', 'order_id'),

        // Clear cart on successful payment
        'clear_on_success' => env('CASHIER_CART_CLEAR_ON_SUCCESS', true),

        // Handle payment failures
        'handle_failure' => env('CASHIER_CART_HANDLE_FAILURE', true),

        // Failure handling mode: immediate_release|retry_window|hybrid
        'failure_mode' => env('CASHIER_CART_FAILURE_MODE', 'hybrid'),

        // Retry window in minutes (for retry_window mode)
        'retry_window_minutes' => env('CASHIER_CART_RETRY_WINDOW', 15),

        // Error codes that trigger immediate inventory release (for hybrid mode)
        'hard_failure_codes' => [
            'card_declined',
            'insufficient_funds',
            'expired_card',
            'incorrect_cvc',
            'processing_error',
        ],

        // Allocate inventory before payment
        'allocate_inventory' => env('CASHIER_CART_ALLOCATE_INVENTORY', true),

        // Inventory allocation TTL in minutes
        'inventory_ttl_minutes' => env('CASHIER_CART_INVENTORY_TTL', 30),

        // Validate stock before checkout
        'validate_stock' => env('CASHIER_CART_VALIDATE_STOCK', true),
    ],
];
