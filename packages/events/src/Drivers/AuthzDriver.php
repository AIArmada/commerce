<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\Events\Contracts\EventAuthzDriver;
use AIArmada\Events\Data\EventAssignmentData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

final class AuthzDriver implements EventAuthzDriver
{
    public function syncAssignment(EventAssignmentData $assignment): void
    {
        if (! interface_exists(RoleAdministrationApi::class) || ! config('authz.scopes.enabled', false)) {
            return;
        }

        $manageable = $this->model($assignment->manageableType, $assignment->manageableId);

        if (! $manageable instanceof Model) {
            return;
        }

        $scopeId = app(RoleAdministrationApi::class)->resolveRecordScope(new RecordReference($manageable->getMorphClass(), (string) $manageable->getKey()));

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
        $assigned = app(RoleAdministrationApi::class)->assignExistingRole(
            new RecordReference($manager->getMorphClass(), (string) $manager->getKey()),
            $roleName,
            (string) config('authz.guards.0', 'web'),
            $scopeId,
        );

        if (! $assigned) {
            Log::warning('Skipping management assignment authz sync because the role does not exist in the resolved scope.', [
                'role' => $roleName,
                'scope_id' => $scopeId,
                'manager_type' => $manager->getMorphClass(),
                'manager_id' => (string) $manager->getKey(),
            ]);

            return;
        }

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
