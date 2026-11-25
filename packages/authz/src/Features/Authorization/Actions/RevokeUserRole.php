<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

final readonly class RevokeUserRole
{
    public function execute(Model $user, string $roleName): void
    {
        if (! method_exists($user, 'removeRole')) {
            throw new InvalidArgumentException('The user model must support role removal.');
        }

        try {
            $user->removeRole($roleName);
        } catch (RoleDoesNotExist) {
            // An already absent role requires no mutation.
        }
    }
}
