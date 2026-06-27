<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Pricing',
        'settings_group' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'promotions' => true,  // Register PromotionResource in Pricing context
    ],

    'resources' => [
        'navigation_sort' => [
            'price_lists' => 1,
            'promotions' => 2,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'settings' => 10,
            'price_simulator' => 99,
        ],
    ],
];
