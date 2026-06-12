<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventManagementAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SyncManagementAssignmentToAuthzAction
{
    public function handle(EventManagementAssignment $assignment): void
    {
        if (! $this->filamentAuthzAvailable()) {
            return;
        }

        $manageable = $assignment->manageable;

        if (! $manageable instanceof Model) {
            return;
        }

        $resolverClass = 'AIArmada\\FilamentAuthz\\Support\\AuthzScopeResolver';

        if (! class_exists($resolverClass)) {
            return;
        }

        $scopeId = $resolverClass::resolveId($manageable);

        if ($scopeId === null) {
            return;
        }

        $manager = $assignment->manager;

        if (! $manager instanceof Model) {
            return;
        }

        $this->assignManagerToScope($manager, $scopeId);
    }

    private function assignManagerToScope(Model $manager, string|int $scopeId): void
    {
        $roleClass = 'Spatie\\Permission\\Models\\Role';
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key', 'role_id');
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

        $role = app($roleClass)
            ->where('name', config('filament-authz.panel_user.name', 'panel_user'))
            ->first();

        if ($role === null) {
            return;
        }

        DB::table($pivotTable)->updateOrInsert(
            [
                $roleKey => $role->getKey(),
                $modelMorphKey => $manager->getKey(),
                'model_type' => $manager->getMorphClass(),
                'team_id' => $scopeId,
            ],
            [],
        );
    }

    private function filamentAuthzAvailable(): bool
    {
        return class_exists('AIArmada\\FilamentAuthz\\Support\\AuthzScopeResolver')
            && config('filament-authz.authz_scopes.enabled', false);
    }
}
