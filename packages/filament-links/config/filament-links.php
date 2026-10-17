<?php

declare(strict_types=1);

return [
    /* Restrict resource access when one or more staff roles are configured. */
    'staff_roles' => [],

    /* Subject models whose tracked links are managed by another workflow. */
    'protected_subject_types' => null,

    /* Navigation */
    'navigation' => [
        'group' => 'Links',
    ],

    /* Features */
    'features' => [
        'links' => true,
    ],

    /* Resources */
    'resources' => [
        'navigation_sort' => [
            'links' => 10,
        ],
    ],
];
