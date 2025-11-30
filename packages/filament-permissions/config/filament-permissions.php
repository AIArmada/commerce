<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Settings',
        'sort' => 99,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'permissions' => 'heroicon-o-key',
            'users' => 'heroicon-o-users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => 'super_admin',
    'default_guard' => 'web',
    'cache_ttl' => 3600,
    'enable_user_resource' => false,
    'features' => [
        'permission_explorer' => false,
        'diff_widget' => false,
        'impersonation_banner' => false,
        'auto_panel_middleware' => false,
        'panel_role_authorization' => false,
    ],
];
