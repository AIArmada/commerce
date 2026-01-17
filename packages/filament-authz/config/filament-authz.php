<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'guards' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => 'super_admin',

    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    'wildcard_permissions' => true,

    'permissions' => [
        'separator' => '.',
        'case' => 'kebab',
    ],

    'resources' => [
        'subject' => 'model',
        'actions' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        'exclude' => [],
    ],

    'pages' => [
        'prefix' => 'page',
        'exclude' => [
            \Filament\Pages\Dashboard::class,
        ],
    ],

    'widgets' => [
        'prefix' => 'widget',
        'exclude' => [
            \Filament\Widgets\AccountWidget::class,
            \Filament\Widgets\FilamentInfoWidget::class,
        ],
    ],

    'custom_permissions' => [],

    'sync' => [
        'permissions' => [],
        'roles' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'register' => true,
        'group' => 'Settings',
        'sort' => 99,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'permissions' => 'heroicon-o-key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'role_resource' => [
        'slug' => 'authz/roles',
        'tabs' => [
            'resources' => true,
            'pages' => true,
            'widgets' => true,
            'custom_permissions' => true,
        ],
        'grid_columns' => 2,
        'checkbox_columns' => 3,
    ],
];
