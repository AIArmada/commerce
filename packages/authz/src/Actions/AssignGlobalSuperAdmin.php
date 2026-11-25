<?php

declare(strict_types=1);

namespace AIArmada\Authz\Actions;

use AIArmada\Authz\Authz;
use AIArmada\Authz\Models\Role;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

final readonly class AssignGlobalSuperAdmin
{
    public function __construct(private Authz $authz, private PermissionRegistrar $registrar) {}

    public function execute(Model & Authenticatable $user, string $guard, string $roleName): void
    {
        if (! $user->exists || ! method_exists($user, 'assignRole') || $roleName === '' || ! is_array(config("auth.guards.{$guard}"))) {
            throw new InvalidArgumentException('A persisted user, configured guard and valid super-admin role are required.');
        }

        $assign = function () use ($user, $guard, $roleName): void {
            $user->getConnection()->transaction(function () use ($user, $guard, $roleName): void {
                $role = Role::findOrCreate($roleName, $guard);
                $user->assignRole($role);
            });
        };

        if ($this->registrar->teams) {
            $this->authz->withScope(null, $assign, $user instanceof Authorizable ? $user : null);
        } else {
            $assign();
        }
    }
}
