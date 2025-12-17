<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Define the database table names used by the customers package.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Database JSON Column Type
    |--------------------------------------------------------------------------
    */
    'json_column_type' => 'json',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model that represents a customer. This should be your
    | application's User model that uses the HasCustomerProfile trait.
    |
    */

    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Segments
    |--------------------------------------------------------------------------
    */

    'segments' => [
        'auto_assign' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Store Credit / Wallet
    |--------------------------------------------------------------------------
    |
    | Configuration for customer wallet and store credit.
    |
    */

    'wallet' => [
        'enabled' => true,
        'currency' => 'MYR',
        'max_balance' => 100000_00, // In cents: RM 100,000
        'min_topup' => 10_00, // In cents: RM 10
    ],

    /*
    |--------------------------------------------------------------------------
    | Wishlists
    |--------------------------------------------------------------------------
    |
    | Configuration for customer wishlists.
    |
    */

    'wishlists' => [
        'enabled' => true,
        'max_items_per_wishlist' => 100,
        'allow_public' => true,
    ],
];
