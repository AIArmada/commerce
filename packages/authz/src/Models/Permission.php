<?php

declare(strict_types=1);

namespace AIArmada\Authz\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission as SpatiePermission;

use function Illuminate\Support\enum_value;

/**
 * Permission model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Role> $roles
 */
final class Permission extends SpatiePermission
{
    use HasUuids;

    /**
     * @throws PermissionAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] ??= Guard::getDefaultName(static::class);
        $attributes['name'] = enum_value($attributes['name']);

        $permission = static::getPermission(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']]);

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    public static function findOrCreate(BackedEnum | string $name, ?string $guardName = null): PermissionContract
    {
        $name = enum_value($name);
        $guardName ??= Guard::getDefaultName(static::class);
        $permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);

        if (! $permission) {
            $attributes = ['name' => $name, 'guard_name' => $guardName];

            return static::query()->create($attributes);
        }

        return $permission;
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.permissions');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }
}
