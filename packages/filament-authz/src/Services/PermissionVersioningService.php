<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Support\PermissionTeamScope;
use Spatie\Permission\PermissionRegistrar;

class PermissionVersioningService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Create a snapshot of current permission state.
     */
    public function createSnapshot(string $name, ?string $description = null): PermissionSnapshot
    {
        $state = [
            'roles' => $this->serializeRoles(),
            'permissions' => $this->serializePermissions(),
            'assignments' => $this->serializeAssignments(),
        ];

        $snapshot = PermissionSnapshot::create([
            'name' => $name,
            'description' => $description,
            'created_by' => auth()->id(),
            'state' => $state,
            'hash' => $this->calculateStateHash($state),
        ]);

        $this->auditLogger->log(
            eventType: AuditEventType::SnapshotCreated,
            metadata: [
                'snapshot_id' => $snapshot->id,
                'name' => $name,
            ]
        );

        return $snapshot;
    }

    /**
     * Compare two snapshots and return the differences.
     *
     * @return array<string, array<string, array<int, mixed>>>
     */
    public function compare(PermissionSnapshot $from, PermissionSnapshot $to): array
    {
        $fromRoles = collect($from->getRoles())->pluck('name')->toArray();
        $toRoles = collect($to->getRoles())->pluck('name')->toArray();

        $fromPermissions = collect($from->getPermissions())->pluck('name')->toArray();
        $toPermissions = collect($to->getPermissions())->pluck('name')->toArray();

        return [
            'roles' => [
                'added' => array_values(array_diff($toRoles, $fromRoles)),
                'removed' => array_values(array_diff($fromRoles, $toRoles)),
            ],
            'permissions' => [
                'added' => array_values(array_diff($toPermissions, $fromPermissions)),
                'removed' => array_values(array_diff($fromPermissions, $toPermissions)),
            ],
            'assignments_changed' => $this->diffAssignments($from, $to),
        ];
    }

    /**
     * Preview what would happen if we rollback to a snapshot.
     *
     * @return array<string, array<string, array<int, mixed>>>
     */
    public function previewRollback(PermissionSnapshot $snapshot): array
    {
        $current = $this->createTemporarySnapshot();

        return $this->compare($current, $snapshot);
    }

    /**
     * Rollback to a previous snapshot.
     */
    public function rollback(PermissionSnapshot $snapshot, bool $dryRun = false): RollbackResult
    {
        if ($dryRun) {
            return new RollbackResult(
                success: true,
                snapshot: $snapshot,
                preview: $this->previewRollback($snapshot),
                isDryRun: true
            );
        }

        DB::transaction(function () use ($snapshot): void {
            $rolesTable = config('permission.table_names.roles', 'roles');
            $permissionsTable = config('permission.table_names.permissions', 'permissions');
            $rolePermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
            $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
            $modelHasPermissionsTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

            $roleIds = Role::query()->select('id');

            // Clear current state
            DB::table($rolePermissionsTable)
                ->whereIn('role_id', $roleIds)
                ->delete();

            $modelRolesQuery = DB::table($modelHasRolesTable);
            PermissionTeamScope::apply($modelRolesQuery, $modelHasRolesTable);
            $modelRolesQuery->delete();

            $modelPermissionsQuery = DB::table($modelHasPermissionsTable);
            PermissionTeamScope::apply($modelPermissionsQuery, $modelHasPermissionsTable);
            $modelPermissionsQuery->delete();

            Role::query()->delete();

            if (! PermissionTeamScope::isEnabled() || PermissionTeamScope::includeGlobal()) {
                DB::table($permissionsTable)->delete();
            }

            // Restore from snapshot
            foreach ($snapshot->getRoles() as $roleData) {
                Role::create($roleData);
            }

            foreach ($snapshot->getPermissions() as $permData) {
                Permission::findOrCreate($permData['name'], $permData['guard_name'] ?? null);
            }

            foreach ($snapshot->getAssignments() as $assignment) {
                $this->restoreAssignment($assignment);
            }
        });

        // Clear cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->auditLogger->log(
            eventType: AuditEventType::SnapshotRestored,
            metadata: [
                'snapshot_id' => $snapshot->id,
                'name' => $snapshot->name,
            ]
        );

        return new RollbackResult(
            success: true,
            snapshot: $snapshot,
            restoredAt: now(),
            isDryRun: false
        );
    }

    /**
     * Get all snapshots.
     *
     * @return Collection<int, PermissionSnapshot>
     */
    public function listSnapshots(): Collection
    {
        return PermissionSnapshot::orderBy('created_at', 'desc')->get();
    }

    /**
     * Delete a snapshot.
     */
    public function deleteSnapshot(PermissionSnapshot $snapshot): bool
    {
        return $snapshot->delete();
    }

    /**
     * Serialize all roles.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeRoles(): array
    {
        return Role::all()->map(function (Role $role) {
            return [
                'name' => $role->name,
                'guard_name' => $role->guard_name,
            ];
        })->toArray();
    }

    /**
     * Serialize all permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializePermissions(): array
    {
        return Permission::all()->map(function (Permission $permission) {
            return [
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ];
        })->toArray();
    }

    /**
     * Serialize all role and permission assignments.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function serializeAssignments(): array
    {
        $assignments = [];

        // Role-permission assignments
        $rolePermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        $rolePermissionsQuery = DB::table($rolePermissionsTable)
            ->join($rolesTable, "{$rolePermissionsTable}.role_id", '=', "{$rolesTable}.id")
            ->join($permissionsTable, "{$rolePermissionsTable}.permission_id", '=', "{$permissionsTable}.id")
            ->select("{$rolesTable}.name as role", "{$permissionsTable}.name as permission");
        PermissionTeamScope::apply($rolePermissionsQuery, $rolesTable);

        $rolePermissions = $rolePermissionsQuery->get();

        foreach ($rolePermissions as $rp) {
            $assignments[] = [
                'type' => 'role_permission',
                'role' => $rp->role,
                'permission' => $rp->permission,
            ];
        }

        // Model-role assignments
        $modelRolesQuery = DB::table($modelHasRolesTable)
            ->join($rolesTable, "{$modelHasRolesTable}.role_id", '=', "{$rolesTable}.id")
            ->select("{$modelHasRolesTable}.model_type", "{$modelHasRolesTable}.model_id", "{$rolesTable}.name as role");
        PermissionTeamScope::apply($modelRolesQuery, $modelHasRolesTable);

        $modelRoles = $modelRolesQuery->get();

        foreach ($modelRoles as $mr) {
            $assignments[] = [
                'type' => 'model_role',
                'model_type' => $mr->model_type,
                'model_id' => $mr->model_id,
                'role' => $mr->role,
            ];
        }

        return $assignments;
    }

    /**
     * Restore an assignment from snapshot data.
     *
     * @param  array<string, mixed>  $assignment
     */
    protected function restoreAssignment(array $assignment): void
    {
        if ($assignment['type'] === 'role_permission') {
            $role = Role::findByName($assignment['role']);
            $permission = Permission::findByName($assignment['permission']);
            if ($role !== null && $permission !== null) {
                $role->givePermissionTo($permission);
            }
        }

        if ($assignment['type'] === 'model_role') {
            $model = $assignment['model_type']::find($assignment['model_id']);
            if ($model !== null && method_exists($model, 'assignRole')) {
                $model->assignRole($assignment['role']);
            }
        }
    }

    /**
     * Calculate hash of the state for comparison.
     *
     * @param  array<string, mixed>  $state
     */
    protected function calculateStateHash(array $state): string
    {
        return md5(json_encode($state) ?: '');
    }

    /**
     * Create a temporary snapshot for comparison without persisting.
     */
    protected function createTemporarySnapshot(): PermissionSnapshot
    {
        $state = [
            'roles' => $this->serializeRoles(),
            'permissions' => $this->serializePermissions(),
            'assignments' => $this->serializeAssignments(),
        ];

        $snapshot = new PermissionSnapshot;
        $snapshot->state = $state;
        $snapshot->hash = $this->calculateStateHash($state);

        return $snapshot;
    }

    /**
     * Diff assignments between two snapshots.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function diffAssignments(PermissionSnapshot $from, PermissionSnapshot $to): array
    {
        $fromAssignments = collect($from->getAssignments());
        $toAssignments = collect($to->getAssignments());

        $fromKeys = $fromAssignments->map(fn ($a) => json_encode($a))->toArray();
        $toKeys = $toAssignments->map(fn ($a) => json_encode($a))->toArray();

        $addedKeys = array_diff($toKeys, $fromKeys);
        $removedKeys = array_diff($fromKeys, $toKeys);

        return [
            'added' => collect($addedKeys)->map(fn ($k) => json_decode($k, true))->values()->toArray(),
            'removed' => collect($removedKeys)->map(fn ($k) => json_decode($k, true))->values()->toArray(),
        ];
    }
}

/**
 * Value object representing a rollback result.
 */
readonly class RollbackResult
{
    /**
     * @param  array<string, mixed>|null  $preview
     */
    public function __construct(
        public bool $success,
        public PermissionSnapshot $snapshot,
        public ?array $preview = null,
        public ?DateTimeInterface $restoredAt = null,
        public bool $isDryRun = false,
    ) {}
}
