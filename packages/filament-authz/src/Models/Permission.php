<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

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
