<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Permission;

final readonly class PersistPermission
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Permission
    {
        return Permission::query()->create($attributes);
    }
}
