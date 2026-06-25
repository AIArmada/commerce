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
        'settings_group' => 'Settings',
        'sort' => 100,
        'permission' => 'manage-commerce-navigation',
    ],
];
