<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncUserPermissions
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** @param list<string> $permissionIds */
    public function execute(RecordReference $record, array $permissionIds, string $guard): void
    {
        $user = EloquentRecordReference::resolve($record);
        if (! $user instanceof Model || ! method_exists($user, 'permissions') || ! Guard::getNames($user)->contains($guard)) {
            throw new AuthorizationException('The selected user does not support this permission guard.');
        }

        $permissionIds = array_values(array_unique($permissionIds));
        $permissionClass = $this->registrar->getPermissionClass();
        $permissionModel = new $permissionClass;
        if ($permissionModel instanceof \AIArmada\Authz\Features\Authorization\Models\Permission) {
            foreach ($permissionIds as $id) {
                if (! Str::isUuid($id)) {
                    throw new AuthorizationException('Invalid authorization identifier.');
                }
            }
        }

        $connection = $permissionModel->getConnection();

        [$added, $removed] = $connection->transaction(function () use ($user, $permissionIds, $guard, $permissionModel, $connection): array {
            if ($user->getConnection() === $connection) {
                $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            }

            $eligible = $permissionModel->newQuery()->where('guard_name', $guard);
            if ((clone $eligible)->whereKey($permissionIds)->orderBy($permissionModel->getKeyName())->lockForUpdate()->get([$permissionModel->getKeyName()])->count() !== count($permissionIds)) {
                throw new AuthorizationException('One or more selected permissions are invalid for the current guard.');
            }

            $pivotKey = $this->registrar->pivotPermission;
            $identity = [
                (string) config('permission.column_names.model_morph_key', 'model_id') => $user->getKey(),
                'model_type' => $user->getMorphClass(),
            ];

            if ($this->registrar->teams) {
                $identity[$this->registrar->teamsKey] = $this->registrar->getPermissionsTeamId();
            }

            $table = (string) config('permission.table_names.model_has_permissions', 'model_has_permissions');
            $pivot = $connection->table($table)->where($identity)->whereIn($pivotKey, $eligible->select($permissionModel->getKeyName()));
            $current = (clone $pivot)->pluck($pivotKey)->map(static fn (mixed $id): string => (string) $id)->all();
            $removed = array_values(array_diff($current, $permissionIds));
            if ($removed !== []) {
                (clone $pivot)->whereIn($pivotKey, $removed)->delete();
            }

            $added = array_values(array_diff($permissionIds, $current));
            if ($added !== []) {
                $connection->table($table)->insert(array_map(static fn (string $id): array => [...$identity, $pivotKey => $id], $added));
            }

            return [$added, $removed];
        });

        $user->unsetRelation('permissions');
        $this->registrar->forgetCachedPermissions();

        if (config('permission.events_enabled', false)) {
            if ($removed !== []) {
                event(new PermissionDetachedEvent($user, $removed));
            }
            if ($added !== []) {
                event(new PermissionAttachedEvent($user, $added));
            }
        }
    }
}
