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
];
