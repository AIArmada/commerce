<?php

declare(strict_types=1);

return [

    // Database
    'database' => [
        'tables' => [
            'customers' => 'customers',
            'addresses' => 'customer_addresses',
            'segments' => 'customer_segments',
            'segment_customer' => 'customer_segment_customer',
            'groups' => 'customer_groups',
            'group_members' => 'customer_group_members',
            'wishlists' => 'wishlists',
            'wishlist_items' => 'wishlist_items',
            'notes' => 'customer_notes',
        ],
        'json_column_type' => 'json',
    ],

    // Integrations
    'integrations' => [
        'user_model' => App\Models\User::class,
    ],

    // Defaults
    'defaults' => [
        'wallet' => [
            'currency' => 'MYR',
            'max_balance' => 100000_00, // In cents: RM 100,000
            'min_topup' => 10_00, // In cents: RM 10
        ],
        'wishlists' => [
            'max_items_per_wishlist' => 100,
        ],
    ],

    // Features
    'features' => [
        'segments' => [
            'auto_assign' => true,
        ],
        'wallet' => [
            'enabled' => true,
        ],
        'wishlists' => [
            'enabled' => true,
            'allow_public' => true,
        ],
    ],
];
