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
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        $query = Role::query()->where('name', $name)->where('guard_name', $guard);

        if ($teamId !== null) {
            $query->where($teamsKey, $teamId);
        } else {
            $query->whereNull($teamsKey);
        }

        return $query->firstOrCreate([
            'name' => $name,
            'guard_name' => $guard,
            $teamsKey => $teamId,
        ]);
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
            if ($teamId !== null) {
                setPermissionsTeamId($teamId);
            }

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
            if ($teamId !== null) {
                setPermissionsTeamId($teamId);
            }

            /** @phpstan-ignore method.notFound */
            $user->removeRole($role->spatieRoleName());
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
