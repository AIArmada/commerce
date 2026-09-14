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
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Pricing settings are global (shared by every tenant), so the settings
    | page requires this ability. Define it with a Gate in the host app.
    |
    */
    'authorization' => [
        'settings_ability' => 'pricing.manage-settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'price_lists' => 1,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'settings' => 10,
            'price_simulator' => 99,
        ],
    ],
];
