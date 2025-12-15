<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'prices' => 'prices',
        'price_lists' => 'price_lists',
        'price_tiers' => 'price_tiers',
        'promotions' => 'promotions',
        'promotionables' => 'promotionables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database JSON Column Type
    |--------------------------------------------------------------------------
    */
    'json_column_type' => env('PRICING_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),

    /*
    |--------------------------------------------------------------------------
    | Owner (Multi-tenancy)
    |--------------------------------------------------------------------------
    | When enabled, pricing data is automatically scoped to the owner.
    */
    'owner' => [
        'enabled' => env('PRICING_OWNER_ENABLED', false),
    ],
];
