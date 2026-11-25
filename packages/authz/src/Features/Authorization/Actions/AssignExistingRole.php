<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\PermissionRegistrar;

final readonly class AssignExistingRole
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function execute(RecordReference $record, string $name, string $guard, string | int | null $scope): bool
    {
        if (! $this->registrar->teams && $scope !== null) {
            return false;
        }

        $query = Role::query()->where('name', $name)->where('guard_name', $guard);

        if ($this->registrar->teams) {
            $query->where($this->registrar->teamsKey, $scope);
        }

        $role = $query->first();

        if ($role === null) {
            return false;
        }

        try {
            $model = EloquentRecordReference::resolve($record);
        } catch (ModelNotFoundException) {
            return false;
        }

        if ($model === null) {
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

        $connection = $role->getConnection();
        $inserted = $connection->transaction(function () use ($connection, $role, $attributes): bool {
            Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $pivot = $connection->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'));

            if ((clone $pivot)->where($attributes)->exists()) {
                return false;
            }

            return $pivot->insert($attributes);
        });

        if ($inserted) {
            $model->unsetRelation('roles')->unsetRelation('permissions');
            $this->registrar->forgetCachedPermissions();

            if (config('permission.events_enabled', false)) {
                event(new RoleAttachedEvent($model, [$role->getKey()]));
            }
        }

        return true;
    }
}
