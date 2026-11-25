<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Authz\Models\Role;
use AIArmada\Events\Contracts\EventAuthzDriver;
use AIArmada\Events\Data\EventAssignmentData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

final class AuthzDriver implements EventAuthzDriver
{
    private const string SCOPE_RESOLVER = 'AIArmada\\Authz\\Support\\AuthzScopeResolver';

    public function syncAssignment(EventAssignmentData $assignment): void
    {
        if (! class_exists(self::SCOPE_RESOLVER) || ! config('authz.scopes.enabled', false)) {
            return;
        }

        $manageable = $this->model($assignment->manageableType, $assignment->manageableId);

        if (! $manageable instanceof Model) {
            return;
        }

        $scopeId = self::SCOPE_RESOLVER::resolveId($manageable);

        if ($scopeId === null) {
            return;
        }

        $manager = $this->model($assignment->managerType, $assignment->managerId);

        if (! $manager instanceof Model) {
            return;
        }

        $this->assignManagerToScope($manager, $scopeId, $assignment->role);
    }

    private function assignManagerToScope(Model $manager, string | int $scopeId, string $roleName): void
    {
        /** @var class-string<Role> $roleClass */
        $roleClass = (string) config('permission.models.role', Role::class);
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key', 'role_id');
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');
        $teamKey = app(PermissionRegistrar::class)->teamsKey;

        $role = $roleClass::query()
            ->where('name', $roleName)
            ->where('guard_name', (string) config('authz.guards.0', 'web'))
            ->where($teamKey, $scopeId)
            ->first();

        if ($role === null) {
            Log::warning('Skipping management assignment authz sync because the role does not exist in the resolved scope.', [
                'role' => $roleName,
                'scope_id' => $scopeId,
                'manager_type' => $manager->getMorphClass(),
                'manager_id' => (string) $manager->getKey(),
            ]);

            return;
        }

        DB::table($pivotTable)->updateOrInsert(
            [
                $roleKey => $role->getKey(),
                $modelMorphKey => $manager->getKey(),
                'model_type' => $manager->getMorphClass(),
                $teamKey => $scopeId,
            ],
            [],
        );

        Log::debug('Synced management assignment to authz role.', [
            'role' => $roleName,
            'scope_id' => $scopeId,
            'manager_type' => $manager->getMorphClass(),
            'manager_id' => (string) $manager->getKey(),
        ]);
    }

    private function model(string $type, string $id): ?Model
    {
        $modelClass = Relation::getMorphedModel($type) ?? $type;

        if (! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $model = $modelClass::query()->find($id);

        return $model instanceof Model ? $model : null;
    }
}
