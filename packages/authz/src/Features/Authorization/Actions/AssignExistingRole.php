<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Spatie\Permission\PermissionRegistrar;

final readonly class AssignExistingRole
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function execute(RecordReference $record, string $name, string $guard, string | int | null $scope): bool
    {
        $model = EloquentRecordReference::resolve($record);
        $query = Role::query()->where('name', $name)->where('guard_name', $guard);

        if ($this->registrar->teams) {
            $query->where($this->registrar->teamsKey, $scope);
        } elseif ($scope !== null) {
            return false;
        }

        $role = $query->first();

        if ($role === null || $model === null) {
            return false;
        }

        $attributes = [
            $this->registrar->pivotRole => $role->getKey(),
            (string) config('permission.column_names.model_morph_key', 'model_id') => $model->getKey(),
            'model_type' => $model->getMorphClass(),
        ];

        if ($this->registrar->teams) {
            $attributes[$this->registrar->teamsKey] = $scope;
        }

        $role->getConnection()->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))->insertOrIgnore($attributes);
        $this->registrar->forgetCachedPermissions();

        return true;
    }
}
