<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    'table_names' => [
        'vouchers' => 'vouchers',
        'voucher_usage' => 'voucher_usage',
        'voucher_wallets' => 'voucher_wallets',
        'voucher_assignments' => 'voucher_assignments',
        'voucher_transactions' => 'voucher_transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'MYR',

    'code' => [
        'auto_uppercase' => env('VOUCHERS_AUTO_UPPERCASE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'max_vouchers_per_cart' => env('VOUCHERS_MAX_PER_CART', 1), // 0=disabled, -1=unlimited
        'replace_when_max_reached' => env('VOUCHERS_REPLACE_WHEN_MAX', true),
        'condition_order' => env('VOUCHERS_CONDITION_ORDER', 50),
        'allow_stacking' => env('VOUCHERS_ALLOW_STACKING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'check_user_limit' => env('VOUCHERS_CHECK_USER_LIMIT', true),
        'check_global_limit' => env('VOUCHERS_CHECK_GLOBAL_LIMIT', true),
        'check_min_cart_value' => env('VOUCHERS_CHECK_MIN_CART_VALUE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'track_applications' => env('VOUCHERS_TRACK_APPLICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
        'resolver' => AIArmada\CommerceSupport\Contracts\NullOwnerResolver::class,
        'include_global' => env('VOUCHERS_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('VOUCHERS_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption
    |--------------------------------------------------------------------------
    */
    'redemption' => [
        'manual_requires_flag' => env('VOUCHERS_MANUAL_REQUIRES_FLAG', true),
        'manual_channel' => 'manual',
    ],
];
