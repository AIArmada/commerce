<?php

declare(strict_types=1);

namespace AIArmada\Membership\Services;

use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;
use AIArmada\Membership\Enums\MemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

final class MembershipRoleSyncService
{
    /**
     * @param  class-string<Model>|null  $subjectClass
     */
    public function ensureExists(
        MemberRole $role,
        ?string $teamId = null,
        ?string $subjectClass = null,
        ?string $guardName = null,
    ): Role
    {
        $name = $role->spatieRoleName();
        $guard = $guardName ?? (string) config('auth.defaults.guard', 'web');
        $registrar = app(PermissionRegistrar::class);
        $attributes = [
            'name' => $name,
            'guard_name' => $guard,
        ];

        if ($registrar->teams) {
            $attributes[$registrar->teamsKey] = $teamId;
        }

        $spatieRole = Role::query()->firstOrCreate($attributes);

        $permissionNames = $role->permissions();

        if ($permissionNames !== []) {
            if ($permissionNames === ['*']) {
                $permissionNames = Permission::query()
                    ->where('guard_name', $guard)
                    ->pluck('name')
                    ->all();
            } else {
                $prefix = $this->resolvePermissionPrefix($subjectClass);

                if ($prefix !== null) {
                    $permissionNames = array_map(fn (string $p): string => "{$prefix}.{$p}", $permissionNames);
                }
            }

            foreach ($permissionNames as $name) {
                Permission::findOrCreate($name, $guard);
            }

            $spatieRole->syncPermissions($permissionNames);
        }

        return $spatieRole;
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

            $spatieRole = $this->ensureExists(
                role: $role,
                teamId: $teamId,
                subjectClass: $subject::class,
                guardName: Guard::getDefaultName($user),
            );

            /** @phpstan-ignore method.notFound */
            $user->assignRole($spatieRole);
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

    /**
     * Resolve the permission prefix from the subject class name.
     * E.g., Institution → 'institution', Speaker → 'speaker'.
     *
     * @param  class-string<Model>|null  $subjectClass
     */
    private function resolvePermissionPrefix(?string $subjectClass): ?string
    {
        if ($subjectClass === null) {
            return null;
        }

        return Str::snake(class_basename($subjectClass));
    }
}
