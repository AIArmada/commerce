<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Permission;

final readonly class EnsurePermissions
{
    /** @param list<string> $permissions */
    public function execute(string $guard, array $permissions): void
    {
        (new Permission)->getConnection()->transaction(function () use ($guard, $permissions): void {
            foreach (array_unique($permissions) as $permission) {
                Permission::findOrCreate($permission, $guard);
            }
        });
    }
}
