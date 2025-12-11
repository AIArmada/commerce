<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Configure the database table names used by the orders package.
    |
    */
    'database' => [
        'tables' => [
            'orders' => 'orders',
            'order_items' => 'order_items',
            'order_addresses' => 'order_addresses',
            'order_payments' => 'order_payments',
            'order_refunds' => 'order_refunds',
            'order_notes' => 'order_notes',
        ],
        'json_column_type' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Number Generation
    |--------------------------------------------------------------------------
    |
    | Configure how order numbers are generated.
    |
    */
    'order_number' => [
        'prefix' => 'ORD',
        'separator' => '-',
        'length' => 8,
        'use_date' => true,
        'date_format' => 'Ymd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for orders.
    |
    */
    'currency' => [
        'default' => 'MYR',
        'decimal_places' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Configure audit trail for orders (using owen-it/laravel-auditing).
    |
    */
    'audit' => [
        'enabled' => true,
        'threshold' => 500, // Keep extensive history for compliance
    ],

    /*
    |--------------------------------------------------------------------------
    | State Machine
    |--------------------------------------------------------------------------
    |
    | Configure order state machine behavior.
    |
    */
    'states' => [
        'default' => 'created',
        'log_transitions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configure optional package integrations.
    |
    */
    'integrations' => [
        'inventory' => [
            'enabled' => true,
            'deduct_on' => 'payment_confirmed', // When to deduct inventory
        ],
        'affiliates' => [
            'enabled' => true,
            'attribute_on' => 'payment_confirmed', // When to attribute commissions
        ],
        'shipping' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure order notifications.
    |
    */
    'notifications' => [
        'order_confirmed' => true,
        'payment_received' => true,
        'order_shipped' => true,
        'order_delivered' => true,
        'order_canceled' => true,
    ],
];
