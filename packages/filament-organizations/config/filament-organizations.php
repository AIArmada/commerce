<?php

declare(strict_types=1);

return [
    'navigation' => [
        'group' => 'Organizations',
        'sort' => 10,
    ],

    'resources' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Any authenticated user may create organizations, so creations are
    | throttled per user to slow down organization spam.
    |
    */
    'rate_limits' => [
        'create_per_hour' => 10,
    ],
];
