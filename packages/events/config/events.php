<?php

declare(strict_types=1);
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

$tablePrefix = env('EVENTS_TABLE_PREFIX', 'event_');

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'series' => $tablePrefix . 'series',
            'events' => 'events',
            'venues' => $tablePrefix . 'venues',
            'occurrences' => $tablePrefix . 'occurrences',
            'registrations' => $tablePrefix . 'registrations',
        ],
    ],

    'features' => [
        'owner' => [
            'enabled' => env('EVENTS_OWNER_ENABLED', false),
            'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
        ],
    ],

    'codes' => [
        'registration_prefix' => env('EVENTS_REGISTRATION_PREFIX', 'REG'),
        'registration_length' => (int) env('EVENTS_REGISTRATION_LENGTH', 10),
    ],

    'integrations' => [
        'product_model' => Product::class,
        'variant_model' => Variant::class,
        'customer_model' => Customer::class,
        'order_model' => Order::class,
        'order_item_model' => OrderItem::class,
    ],
];
