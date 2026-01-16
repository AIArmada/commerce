<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission model extending Spatie Permission with UUID support.
 *
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 */
final class Permission extends SpatiePermission
{
    use HasUuids;

    public function getTable(): string
    {
        $table = config('permission.table_names.permissions');

        if (is_string($table) && $table !== '') {
            return $table;
        }

        return parent::getTable();
    }
}
