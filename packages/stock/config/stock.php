<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Stock Transaction Table
    |--------------------------------------------------------------------------
    |
    | The name of the database table that stores stock transactions.
    |
    */
    'table_name' => env('STOCK_TABLE_NAME', 'stock_transactions'),

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | The default threshold for determining when stock is considered low.
    | This can be overridden per model if needed.
    |
    */
    'low_stock_threshold' => env('STOCK_LOW_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    |
    | When the cart package (aiarmada/cart) is installed alongside the stock
    | package, automatic integration is enabled. Stock will be reserved when
    | items are added to cart and released when the cart is cleared.
    |
    */
    'cart' => [
        // Enable automatic cart integration
        'enabled' => env('STOCK_CART_INTEGRATION', true),

        // Default reservation time in minutes
        'reservation_ttl' => env('STOCK_RESERVATION_TTL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Integration
    |--------------------------------------------------------------------------
    |
    | Automatic stock deduction when payment succeeds. Works with CashierChip
    | and can be configured for custom payment event classes.
    |
    */
    'payment' => [
        // Automatically deduct stock on payment success
        'auto_deduct' => env('STOCK_AUTO_DEDUCT', true),

        // Additional payment success event classes to listen for
        'events' => [
            // 'App\Events\OrderPaid',
            // 'App\Events\PaymentCompleted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Control which stock events are dispatched.
    |
    */
    'events' => [
        // Dispatch LowStockDetected event
        'low_stock' => env('STOCK_EVENT_LOW_STOCK', true),

        // Dispatch OutOfStock event
        'out_of_stock' => env('STOCK_EVENT_OUT_OF_STOCK', true),

        // Dispatch StockReserved event
        'reserved' => env('STOCK_EVENT_RESERVED', true),

        // Dispatch StockReleased event
        'released' => env('STOCK_EVENT_RELEASED', true),

        // Dispatch StockDeducted event
        'deducted' => env('STOCK_EVENT_DEDUCTED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    |
    | Settings for automatic cleanup of expired reservations.
    |
    */
    'cleanup' => [
        // Schedule cleanup command (add to app/Console/Kernel.php)
        // $schedule->command('stock:cleanup-reservations')->everyFiveMinutes();

        // Keep expired reservations for debugging (0 = delete immediately)
        'keep_expired_for_minutes' => env('STOCK_KEEP_EXPIRED', 0),
    ],
];
