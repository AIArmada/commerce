<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final readonly class DeleteRoleDefinition
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function execute(string $id): bool
    {
        /** @var class-string<Role> $modelClass */
        $modelClass = $this->registrar->getRoleClass();
        $model = new $modelClass;
        $connection = $model->getConnection();

        $connection->transaction(function () use ($id, $model, $connection): void {
            $record = $model->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();

            if (! $record->delete()) {
                throw new RuntimeException('Authorization definition deletion was rejected.');
            }

            $pivot = $this->registrar->pivotRole;
            foreach (['model_has_roles', 'role_has_permissions'] as $table) {
                $connection->table((string) config("permission.table_names.{$table}", $table))
                    ->where($pivot, $id)
                    ->delete();
            }
        });

        $this->registrar->forgetCachedPermissions();

        return true;
    }
}
