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
     * Request-scoped verdict cache keyed by model identity and role name.
     *
     * @var array<string, bool>
     */
    private static array $globalRoleCache = [];

    /**
     * Request-scoped verdict cache for non-model users.
     *
     * @var WeakMap<object, array<string, bool>>|null
     */
    private static ?WeakMap $objectRoleCache = null;

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

        $cached = self::cachedVerdict($user, $role);

        if ($cached !== null) {
            return $cached;
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

        self::storeVerdict($user, $role, $verdict);

        return $verdict;
    }

    public static function flushGlobalRoleCache(): void
    {
        self::$globalRoleCache = [];
        self::$objectRoleCache = null;
    }

    /**
     * Invalidate the request caches whenever a permission or role assignment
     * is written, so same-request grants/revocations remain visible without
     * re-querying on every subsequent authorization check.
     */
    public static function registerMutationListener(): void
    {
        DB::listen(static function (QueryExecuted $query): void {
            $sql = mb_ltrim($query->sql);

            if (! preg_match('/^(?:insert\s+into|update|delete\s+from)\s+["`]?([a-zA-Z0-9_.]+)["`]?/i', $sql, $matches)) {
                return;
            }

            $table = $matches[1];
            $dot = mb_strrpos($table, '.');

            if ($dot !== false) {
                $table = mb_substr($table, $dot + 1);
            }

            $table = mb_trim($table, '"`');

            $permissionTables = [
                (string) config('permission.table_names.roles', 'roles'),
                (string) config('permission.table_names.permissions', 'permissions'),
                (string) config('permission.table_names.model_has_roles', 'model_has_roles'),
                (string) config('permission.table_names.model_has_permissions', 'model_has_permissions'),
                (string) config('permission.table_names.role_has_permissions', 'role_has_permissions'),
            ];

            if (! in_array($table, $permissionTables, true)) {
                return;
            }

            self::flushGlobalRoleCache();
            UserWildcardPermissions::flushCache();
        });
    }

    private static function cachedVerdict(object $user, string $role): ?bool
    {
        if ($user instanceof Model) {
            $key = self::modelCacheKey($user, $role);

            if ($key === null) {
                return null;
            }

            return array_key_exists($key, self::$globalRoleCache)
                ? self::$globalRoleCache[$key]
                : null;
        }

        $cache = self::$objectRoleCache ??= new WeakMap;
        $roles = $cache[$user] ?? [];

        return array_key_exists($role, $roles) ? $roles[$role] : null;
    }

    private static function storeVerdict(object $user, string $role, bool $verdict): void
    {
        if ($user instanceof Model) {
            $key = self::modelCacheKey($user, $role);

            if ($key !== null) {
                self::$globalRoleCache[$key] = $verdict;
            }

            return;
        }

        $cache = self::$objectRoleCache ??= new WeakMap;
        $roles = $cache[$user] ?? [];
        $roles[$role] = $verdict;
        $cache[$user] = $roles;
    }

    private static function modelCacheKey(Model $user, string $role): ?string
    {
        $key = $user->getKey();

        if ($key === null || $key === '') {
            return null;
        }

        return json_encode([
            $user->getConnection()->getName(),
            $user->getConnection()->getDatabaseName(),
            $user->getTable(),
            $user->getMorphClass(),
            $key,
            $role,
        ], JSON_THROW_ON_ERROR);
    }
}
