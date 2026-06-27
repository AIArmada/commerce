<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'zones' => true,
        'classes' => true,
        'rates' => true,
        'exemptions' => true,
        'widgets' => true,
        'settings_page' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificates
    |--------------------------------------------------------------------------
    */

    'certificates' => [
        'disk' => env('TAX_CERTIFICATES_DISK', 'local'),
        'directory' => 'tax-exemptions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        'group' => 'Tax',
        'settings_group' => 'Settings',
    ],

    'resources' => [
        'navigation_sort' => [
            'zones' => 1,
            'classes' => 2,
            'rates' => 2,
            'exemptions' => 4,
        ],
    ],

    'pages' => [
        'navigation_sort' => [
            'settings' => 11,
        ],
    ],
];
