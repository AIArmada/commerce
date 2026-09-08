<?php

declare(strict_types=1);

use AIArmada\Organizations\Resolvers\NullCurrentOrganizationResolver;

$tablePrefix = (string) env('ORGANIZATIONS_TABLE_PREFIX', '');

return [
    'database' => [
        'tables' => [
            'organizations' => env('ORGANIZATIONS_TABLE', $tablePrefix . 'organizations'),
            'members' => env('ORGANIZATIONS_MEMBERS_TABLE', $tablePrefix . 'organization_members'),
        ],
    ],

    'resolver' => NullCurrentOrganizationResolver::class,

    'middleware' => [
        'require_context' => env('ORGANIZATIONS_REQUIRE_CONTEXT', true),
    ],
];
