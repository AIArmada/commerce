<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
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
        $pivotTable = (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolePivotKey = (string) config('permission.column_names.role_pivot_key', 'role_id');
        $permissionPivotKey = (string) config('permission.column_names.permission_pivot_key', 'permission_id');

        /** @var BelongsToMany<Permission, $this> $relation */
        $relation = $this->belongsToMany(Permission::class, $pivotTable, $rolePivotKey, $permissionPivotKey);

        return $relation;
    }

    public function getTable(): string
    {
        $table = config('permission.table_names.roles');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }
}
