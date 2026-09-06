<?php

declare(strict_types=1);

return [
    'guards' => ['web'],
    'super_admin_role' => 'super_admin',
    'wildcard_permissions' => true,
    'permissions' => [
        'separator' => '.',
        'case' => 'camel',
    ],
    'scopes' => [
        'enabled' => false,
        'auto_create' => true,
        'enforce' => true,
    ],
    'impersonate' => [
        'guard' => 'web',
    ],
];
