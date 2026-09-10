<?php

declare(strict_types=1);

namespace AIArmada\Membership\Services;

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Membership\Enums\MemberRole;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membership owns the mapped role assignment for a member in a subject scope.
 *
 * Role-definition reconciliation is additive by default: missing mapped roles
 * and permissions are created, while permissions already attached to those
 * roles are retained. Passing $prune = true makes the configured permission
 * list authoritative and removes unconfigured permissions from mapped roles.
 * Neither mode removes roles assigned to users.
 *
 * Member assignment is additive for unrelated user roles. A membership role
 * change or removal revokes only the previous mapped role in the current
 * subject team. Authz cannot distinguish that same mapped role from a direct
 * external grant in the same team, so such a grant is treated as the
 * membership-owned assignment; independent grants must use a different role
 * name. Other team assignments are preserved.
 */
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
        bool $prune = false,
    ): Role {
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

        if ($permissionNames !== []) {
            foreach ($permissionNames as $name) {
                Permission::findOrCreate($name, $guard);
            }
        }

        if ($prune) {
            $spatieRole->syncPermissions($permissionNames);
        } elseif ($permissionNames !== []) {
            $spatieRole->givePermissionTo($permissionNames);
        }

        return $spatieRole;
    }

    public function syncAll(bool $prune = false): int
    {
        $count = 0;

        foreach (MemberRole::cases() as $role) {
            $this->ensureExists($role, prune: $prune);
            $count++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $count;
    }

    public function assignToUser(Model $subject, Model $user, MemberRole $role): void
    {
        $this->inSubjectTeam($subject, function (int | string | null $teamId) use ($role, $subject, $user): void {
            $spatieRole = $this->ensureExists(
                role: $role,
                teamId: $teamId,
                subjectClass: $subject::class,
                guardName: Guard::getDefaultName($user),
            );

            /** @phpstan-ignore method.notFound */
            $user->assignRole($spatieRole);
        });
    }

    public function revokeFromUser(Model $subject, Model $user, MemberRole $role): void
    {
        $this->inSubjectTeam($subject, function () use ($role, $user): void {
            /** @phpstan-ignore method.notFound */
            $user->removeRole($role->spatieRoleName());
        });
    }

    /**
     * Run a member role mutation with the authz team resolver pointed at the
     * subject, restoring the previous resolver state even when the mutation
     * fails.
     *
     * @template TResult
     *
     * @param  Closure(int|string|null): TResult  $callback
     * @return TResult
     */
    private function inSubjectTeam(Model $subject, Closure $callback): mixed
    {
        $teamId = $this->subjectTeamId($subject);
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($teamId);

            if ($teamId !== null && (string) getPermissionsTeamId() !== (string) $teamId) {
                throw new LogicException('The authz team context does not match the membership subject.');
            }

            return $callback($teamId);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    private function subjectTeamId(Model $subject): int | string | null
    {
        if (! config('membership.features.team_scoped_roles', true)) {
            return null;
        }

        $teamId = $subject->getKey();

        if ((! is_int($teamId) && ! is_string($teamId)) || $teamId === '') {
            throw new LogicException('A membership subject must have a non-empty scalar key for team-scoped roles.');
        }

        return $teamId;
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
