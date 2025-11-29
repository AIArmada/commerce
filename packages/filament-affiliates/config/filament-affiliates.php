<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => 'E-commerce',

    'resources' => [
        'navigation_sort' => [
            'affiliates' => 60,
            'affiliate_conversions' => 61,
            'affiliate_payouts' => 62,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */

    'widgets' => [
        'show_conversion_rate' => true,
        'currency' => env('AFFILIATES_DEFAULT_CURRENCY', 'USD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configure if the plugin should render deep links when Filament Cart or
    | Filament Vouchers are detected at runtime.
    |
    */

    'integrations' => [
        'filament_cart' => true,
        'filament_vouchers' => true,
    ],

];
