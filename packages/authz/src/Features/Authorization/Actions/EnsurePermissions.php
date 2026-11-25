<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final readonly class EnsurePermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** @param list<string> $permissions */
    public function execute(string $guard, array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));

        if ($permissions === []) {
            return;
        }

        $permissionClass = $this->registrar->getPermissionClass();
        $permissionModel = new $permissionClass;
        $connection = $permissionModel->getConnection();
        $table = $permissionModel->getTable();
        $keyName = $permissionModel->getKeyName();
        $usesUuids = in_array(HasUuids::class, class_uses_recursive($permissionModel), true);
        $usesTimestamps = $permissionModel->usesTimestamps();

        $missing = $connection->transaction(function () use ($connection, $table, $permissionModel, $guard, $permissions, $keyName, $usesUuids, $usesTimestamps): array {
            $existing = $permissionModel->newQuery()
                ->where('guard_name', $guard)
                ->whereIn('name', $permissions)
                ->pluck('name')
                ->all();

            $missing = array_values(array_diff($permissions, $existing));

            if ($missing === []) {
                return [];
            }

            $now = CarbonImmutable::now();

            foreach (array_chunk($missing, 500) as $chunk) {
                $rows = array_map(static function (string $name) use ($keyName, $guard, $usesUuids, $usesTimestamps, $now): array {
                    $row = ['name' => $name, 'guard_name' => $guard];

                    if ($usesUuids) {
                        $row[$keyName] = (string) Str::uuid();
                    }

                    if ($usesTimestamps) {
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                    }

                    return $row;
                }, $chunk);

                $connection->table($table)->insertOrIgnore($rows);
            }

            return $missing;
        });

        if ($missing !== []) {
            $this->registrar->forgetCachedPermissions();
        }
    }
}
