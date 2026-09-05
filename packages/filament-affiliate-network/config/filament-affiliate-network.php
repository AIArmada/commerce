<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Affiliate Network',
        'sort' => 50,
    ],

    'authorization' => [
        'admin_ability' => 'affiliate-network.admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    */
    'marketplace' => [
        'show_commission_rates' => true,
        'show_cookie_duration' => true,
    ],
];
