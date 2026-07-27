<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'enabled' => env('FILAMENT_COMMERCE_NAVIGATION_ENABLED', true),
        'group' => 'Commerce',
        'settings_group' => 'Settings',
        'sort' => 100,
        'permission' => 'manage-commerce-navigation',
        'icons' => [
            'currencies' => 'heroicon-o-currency-dollar',
            'languages' => 'heroicon-o-language',
            'timezones' => 'heroicon-o-globe-alt',
        ],
    ],

    'resources' => [
        'currencies' => [
            'read_only' => true,
        ],
        'languages' => [
            'read_only' => true,
        ],
        'timezones' => [
            'read_only' => true,
        ],
    ],
];
