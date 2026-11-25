<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Models\Role;

final readonly class PersistRole
{
    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Role
    {
        return Role::query()->create($attributes);
    }
}
