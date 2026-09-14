<?php

declare(strict_types=1);

namespace AIArmada\Authz\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;

use function Illuminate\Support\enum_value;

/**
 * Role model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 */
final class Role extends SpatieRole
{
    use HasUuids;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var BelongsToMany<Permission, $this> $relation */
        $relation = $this->belongsToMany(
            Config::permissionModel(),
            Config::roleHasPermissionsTable(),
            $registrar->pivotRole,
            $registrar->pivotPermission
        );

        return $relation;
    }

    public function authzScope(): BelongsTo
    {
        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        return $this->belongsTo(AuthzScope::class, $teamsKey);
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.roles');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }

    /**
     * @throws RoleAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);
        $attributes['name'] = enum_value($attributes['name']);

        $params = ['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']];

        $registrar = app(PermissionRegistrar::class);

        if ($registrar->teams) {
            $teamsKey = $registrar->teamsKey;

            if (config('authz.scopes.enforce', true)) {
                $teamId = getPermissionsTeamId();

                if ($teamId !== null && ! array_key_exists($teamsKey, $attributes)) {
                    $attributes[$teamsKey] = $teamId;
                }
            }

            if (array_key_exists($teamsKey, $attributes)) {
                $params[$teamsKey] = $attributes[$teamsKey];
            }
        }

        if (static::findByParam($params)) {
            throw RoleAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * @return RoleContract|Role|null
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $query = static::query();

        $registrar = app(PermissionRegistrar::class);

        if ($registrar->teams) {
            $teamsKey = $registrar->teamsKey;
            $teamId = $params[$teamsKey] ?? getPermissionsTeamId();

            if (config('authz.scopes.enforce', true)) {
                if ($teamId === null) {
                    $query->whereNull($teamsKey);
                } else {
                    $query->where($teamsKey, $teamId);
                }
            } else {
                $query->where(fn ($q) => $q->whereNull($teamsKey)
                    ->orWhere($teamsKey, $teamId));
            }

            unset($params[$teamsKey]);
        }

        $allowedKeys = ['name', 'guard_name', (new static)->getKeyName()];

        foreach ($params as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException("Unexpected role lookup parameter [{$key}].");
            }

            $query->where($key, $value);
        }

        return $query->first();
    }
}
