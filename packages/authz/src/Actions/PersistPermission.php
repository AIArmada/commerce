<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\Permission;

final readonly class PersistPermission
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Permission
    {
        return Permission::query()->create($attributes);
    }
}
