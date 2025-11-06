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
    | Transaction Types
    |--------------------------------------------------------------------------
    |
    | The available transaction types for stock movements.
    |
    */
    'transaction_types' => [
        'in' => 'Stock In',
        'out' => 'Stock Out',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Reasons
    |--------------------------------------------------------------------------
    |
    | Common reasons for stock transactions. These can be extended.
    |
    */
    'transaction_reasons' => [
        'restock' => 'Restock',
        'sale' => 'Sale',
        'return' => 'Return',
        'adjustment' => 'Adjustment',
        'damaged' => 'Damaged',
        'initial' => 'Initial Stock',
    ],

    /*
    |--------------------------------------------------------------------------
    | Use Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Whether to use soft deletes for stock transactions.
    |
    */
    'use_soft_deletes' => env('STOCK_USE_SOFT_DELETES', false),
];
