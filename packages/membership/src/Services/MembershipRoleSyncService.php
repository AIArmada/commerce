<?php

declare(strict_types=1);

namespace AIArmada\Membership\Services;

use AIArmada\CommerceSupport\Models\Role;
use AIArmada\Membership\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class MembershipRoleSyncService
{
    public function ensureExists(MemberRole $role, ?string $teamId = null): Role
    {
        $name = $role->spatieRoleName();
        $guard = (string) config('auth.defaults.guard', 'web');
        $registrar = app(PermissionRegistrar::class);
        $attributes = [
            'name' => $name,
            'guard_name' => $guard,
        ];

        if ($registrar->teams) {
            $attributes[$registrar->teamsKey] = $teamId;
        }

        return Role::query()->firstOrCreate($attributes);
    }

    public function syncAll(): int
    {
        $count = 0;

        foreach (MemberRole::cases() as $role) {
            $this->ensureExists($role, null);
            $count++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $count;
    }

    public function assignToUser(Model $subject, Model $user, MemberRole $role): void
    {
        $teamId = config('membership.features.team_scoped_roles', true)
            ? $subject->getKey()
            : null;
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($teamId);

            $this->ensureExists($role, $teamId);

            /** @phpstan-ignore method.notFound */
            $user->assignRole($role->spatieRoleName());
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    public function revokeFromUser(Model $subject, Model $user, MemberRole $role): void
    {
        $teamId = config('membership.features.team_scoped_roles', true)
            ? $subject->getKey()
            : null;
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($teamId);

            /** @phpstan-ignore method.notFound */
            $user->removeRole($role->spatieRoleName());
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
