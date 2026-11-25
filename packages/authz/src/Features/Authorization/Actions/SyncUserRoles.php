<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;

final readonly class SyncUserRoles
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * @param  list<string>  $roleIds
     * @param  list<string>|null  $allowedScopeIds
     */
    public function execute(RecordReference $record, array $roleIds, string $guard, bool $restrictToCurrentTeam, string $scopeMode, ?array $allowedScopeIds, bool $includeSuperAdmin): void
    {
        try {
            $user = EloquentRecordReference::resolve($record);
        } catch (ModelNotFoundException) {
            throw new AuthorizationException('The selected user does not exist.');
        }

        if (! $user instanceof Model || ! method_exists($user, 'syncRoles') || ! Guard::getNames($user)->contains($guard)) {
            throw new AuthorizationException('The selected user does not support this role guard.');
        }

        if (! in_array($scopeMode, ['all', 'global_only', 'scoped_only'], true)) {
            throw new AuthorizationException('Invalid role scope mode.');
        }

        $roleIds = array_values(array_unique($roleIds));
        $roleClass = $this->registrar->getRoleClass();
        $roleModel = new $roleClass;
        if ($roleModel instanceof Role) {
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

        [$added, $removed] = $connection->transaction(function () use ($user, $roleIds, $guard, $restrictToCurrentTeam, $scopeMode, $roleModel, $connection, $teams, $teamsKey, $teamId, $allowedScopeIds, $includeSuperAdmin): array {
            if ($user->getConnection() === $connection) {
                $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            }

            $eligible = $roleModel->newQuery()->where('guard_name', $guard);

            $superAdmin = (string) config('authz.super_admin_role', '');
            if (! $includeSuperAdmin && $superAdmin !== '') {
                $eligible->where('name', '!=', $superAdmin);
            }

            if ($teams && $allowedScopeIds !== null) {
                $eligible->where(fn ($query) => $query->whereNull($teamsKey)->orWhereIn($teamsKey, $allowedScopeIds));
            }

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

            if ($roleIds === []) {
                $selected = $roleModel->newCollection();
            } else {
                $selected = (clone $eligible)->whereKey($roleIds)->orderBy($roleModel->getKeyName())->lockForUpdate()->get($columns);
                if ($selected->count() !== count($roleIds)) {
                    throw new AuthorizationException('One or more selected roles are invalid for the current guard or scope.');
                }
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
            $existingRoles = (! $teams || $current->isEmpty())
                ? $roleModel->newCollection()
                : (clone $eligible)->whereKey($current->pluck($pivotKey)->unique()->all())->get($columns)->keyBy($roleModel->getKeyName());

            $selectedById = $selected->keyBy($roleModel->getKeyName());
            $retained = [];
            $removed = [];
            $added = [];
            $removeGroups = [];

            foreach ($current as $assignment) {
                $id = (string) $assignment->{$pivotKey};
                $scope = $teams ? $assignment->{$teamsKey} : null;

                // A global role may be assigned in a tenant. This form edits
                // canonical role-scope assignments, not those separate grants.
                if ($teams) {
                    $role = $existingRoles->get($id);

                    if ($role === null || (string) $role->getAttribute($teamsKey) !== (string) $scope) {
                        continue;
                    }
                }

                if ($selectedById->has($id)) {
                    $retained[$id] = true;

                    continue;
                }

                $groupKey = $teams ? ($scope === null ? '' : (string) $scope) : 'all';

                $removeGroups[$groupKey] ??= ['scope' => $scope, 'ids' => []];
                $removeGroups[$groupKey]['ids'][] = $id;
                $removed[] = $id;
            }

            foreach ($removeGroups as $group) {
                $delete = (clone $pivot)->whereIn($pivotKey, $group['ids']);

                if ($teams) {
                    if ($group['scope'] === null) {
                        $delete->whereNull($teamsKey);
                    } else {
                        $delete->where($teamsKey, $group['scope']);
                    }
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
                $added[] = $id;
            }

            if ($insert !== []) {
                $connection->table((string) config('permission.table_names.model_has_roles', 'model_has_roles'))->insert($insert);
            }

            return [$added, array_values(array_unique($removed))];
        });

        if ($added !== [] || $removed !== []) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $this->registrar->forgetCachedPermissions();

            if (config('permission.events_enabled', false)) {
                if ($removed !== []) {
                    event(new RoleDetachedEvent($user, $removed));
                }
                if ($added !== []) {
                    event(new RoleAttachedEvent($user, $added));
                }
            }
        }
    }
}
