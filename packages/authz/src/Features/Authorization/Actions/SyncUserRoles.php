<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncUserRoles
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /** @param list<string> $roleIds */
    public function execute(RecordReference $record, array $roleIds, string $guard, bool $restrictToCurrentTeam, string $scopeMode): void
    {
        $user = EloquentRecordReference::resolve($record);
        if (! $user instanceof Model || ! method_exists($user, 'syncRoles') || ! Guard::getNames($user)->contains($guard)) {
            throw new AuthorizationException('The selected user does not support this role guard.');
        }

        if (! in_array($scopeMode, ['all', 'global_only', 'scoped_only'], true)) {
            throw new AuthorizationException('Invalid role scope mode.');
        }

        $roleIds = array_values(array_unique($roleIds));
        $roleClass = $this->registrar->getRoleClass();
        $roleModel = new $roleClass;
        if ($roleModel instanceof \AIArmada\Authz\Features\Authorization\Models\Role) {
            foreach ($roleIds as $id) {
                if (! Str::isUuid($id)) {
                    throw new AuthorizationException('Invalid authorization identifier.');
                }
            }
        }

        $connection = $roleModel->getConnection();
        $teams = $this->registrar->teams;
        $teamsKey = $this->registrar->teamsKey;
        $teamId = $this->registrar->getPermissionsTeamId();

        $connection->transaction(function () use ($user, $roleIds, $guard, $restrictToCurrentTeam, $scopeMode, $roleModel, $connection, $teams, $teamsKey, $teamId): void {
            if ($user->getConnection() === $connection) {
                $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            }

            $eligible = $roleModel->newQuery()->where('guard_name', $guard);

            if ($teams) {
                if ($restrictToCurrentTeam) {
                    $eligible->where($teamsKey, $teamId);
                }

                if ($scopeMode === 'global_only') {
                    $eligible->whereNull($teamsKey);
                } elseif ($scopeMode === 'scoped_only') {
                    $eligible->whereNotNull($teamsKey);
                }
            }

            $columns = [$roleModel->getKeyName()];
            if ($teams) {
                $columns[] = $teamsKey;
            }

            $selected = (clone $eligible)->whereKey($roleIds)->get($columns);
            if ($selected->count() !== count($roleIds)) {
                throw new AuthorizationException('One or more selected roles are invalid for the current guard or scope.');
            }

            $pivotKey = $this->registrar->pivotRole;
            $identity = [
                (string) config('permission.column_names.model_morph_key', 'model_id') => $user->getKey(),
                'model_type' => $user->getMorphClass(),
            ];
            $pivot = $connection->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))->where($identity);

            if ($teams && $restrictToCurrentTeam) {
                $pivot->where($teamsKey, $teamId);
            }

            $current = (clone $pivot)->whereIn($pivotKey, (clone $eligible)->select($roleModel->getKeyName()))->get();
            $existingRoles = (clone $eligible)->whereKey($current->pluck($pivotKey)->unique()->all())->get($columns)->keyBy($roleModel->getKeyName());
            $selectedById = $selected->keyBy($roleModel->getKeyName());
            $retained = [];

            foreach ($current as $assignment) {
                $id = (string) $assignment->{$pivotKey};
                $scope = $teams ? $assignment->{$teamsKey} : null;
                $role = $existingRoles->get($id);

                // A global role may be assigned in a tenant. This form edits
                // canonical role-scope assignments, not those separate grants.
                if ($role === null || ($teams && (string) $role->getAttribute($teamsKey) !== (string) $scope)) {
                    continue;
                }

                if ($selectedById->has($id)) {
                    $retained[$id] = true;
                    continue;
                }

                $delete = (clone $pivot)->where($pivotKey, $id);
                if ($teams) {
                    $delete->where($teamsKey, $scope);
                }

                $delete->delete();
            }

            $insert = [];
            foreach ($selected as $role) {
                $id = (string) $role->getKey();
                if (isset($retained[$id])) {
                    continue;
                }

                $attributes = [...$identity, $pivotKey => $role->getKey()];
                if ($teams) {
                    $attributes[$teamsKey] = $role->getAttribute($teamsKey);
                }

                $insert[] = $attributes;
            }

            if ($insert !== []) {
                $connection->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))->insert($insert);
            }
        });

        $this->registrar->forgetCachedPermissions();
    }
}
