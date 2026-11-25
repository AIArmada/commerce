<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Role;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class AssignUserRole
{
    public function execute(Model $user, string $roleId): void
    {
        if (! method_exists($user, 'assignRole')) {
            throw new InvalidArgumentException('The user model must support role assignment.');
        }

        $user->assignRole(Role::query()->findOrFail($roleId));
    }
}
