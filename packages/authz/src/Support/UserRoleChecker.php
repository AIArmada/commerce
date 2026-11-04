<?php

declare(strict_types=1);

namespace AIArmada\Authz\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use WeakMap;

final class UserRoleChecker
{
    /**
     * Request-scoped verdict cache keyed by user object and role name.
     *
     * @var WeakMap<object, array<string, bool>>|null
     */
    private static ?WeakMap $globalRoleCache = null;

    public static function hasRole(mixed $user, string $role): bool
    {
        return is_object($user)
            && method_exists($user, 'hasRole')
            && (bool) $user->hasRole($role);
    }

    /**
     * Check a role assignment without the active team/scope filter.
     *
     * This is intentionally distinct from hasRole(): a global super-admin
     * assignment must remain global when the current request is team-scoped.
     */
    public static function hasGlobalRole(mixed $user, string $role): bool
    {
        if (! is_object($user) || ! method_exists($user, 'hasRole')) {
            return false;
        }

        $cache = self::$globalRoleCache ??= new WeakMap;
        $cachedRoles = $cache[$user] ?? [];

        if (array_key_exists($role, $cachedRoles)) {
            return $cachedRoles[$role];
        }

        $registrar = app(PermissionRegistrar::class);

        // Drop team scoping entirely for the check: a global assignment may
        // itself carry a team id (single-tenant seeders scope everything to
        // the owner), so filtering by "team null" would miss it. This mirrors
        // FilamentPermission::isSuperAdmin, keeping Gate and UI verdicts
        // coherent.
        //
        // A loaded roles relation can predate a grant, revocation, or sync
        // performed through another instance, so the relation is refreshed
        // for the first check in this request. The verdict is then reused
        // until a role assignment mutation invalidates the request cache;
        // otherwise every Filament navigation item would issue the same
        // count query (100+ per admin page).
        $teams = $registrar->teams;
        $registrar->teams = false;

        $hadLoadedRoles = $user instanceof Model && $user->relationLoaded('roles');
        $loadedRoles = $hadLoadedRoles ? $user->getRelation('roles') : null;

        if ($user instanceof Model) {
            $user->unsetRelation('roles');
        }

        try {
            $verdict = (bool) $user->hasRole($role);
        } finally {
            $registrar->teams = $teams;

            if ($user instanceof Model) {
                $user->unsetRelation('roles');

                if ($hadLoadedRoles) {
                    $user->setRelation('roles', $loadedRoles);
                }
            }
        }

        $cachedRoles[$role] = $verdict;
        $cache[$user] = $cachedRoles;

        return $verdict;
    }

    public static function flushGlobalRoleCache(): void
    {
        self::$globalRoleCache = null;
    }

    /**
     * Invalidate the request cache whenever a role assignment or role record
     * is written, so same-request grants/revocations remain visible without
     * re-querying on every subsequent authorization check.
     */
    public static function registerMutationListener(): void
    {
        DB::listen(static function (QueryExecuted $query): void {
            $sql = mb_ltrim($query->sql);

            if (! preg_match('/^(insert|update|delete)\b/i', $sql)) {
                return;
            }

            $roleTable = (string) config('permission.table_names.roles', 'roles');
            $assignmentTable = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

            if (str_contains($sql, $assignmentTable) || str_contains($sql, $roleTable)) {
                self::flushGlobalRoleCache();
            }
        });
    }
}
