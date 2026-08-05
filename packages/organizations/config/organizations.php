<?php

declare(strict_types=1);

use AIArmada\Organizations\Resolvers\NullCurrentOrganizationResolver;

return [
    'database' => [
        'tables' => [
            'organizations' => env('ORGANIZATIONS_TABLE', 'organizations'),
            'members' => env('ORGANIZATIONS_MEMBERS_TABLE', 'organization_members'),
        ],
    ],

    'resolver' => NullCurrentOrganizationResolver::class,

    'middleware' => [
        'require_context' => false,
    ],
];
