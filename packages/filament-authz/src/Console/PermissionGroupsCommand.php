<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class PermissionGroupsCommand extends Command
{
    protected $signature = 'authz:groups
                            {action? : The action to perform (list, create, show, sync, delete)}
                            {--group= : The group slug for show/sync/delete actions}';

    protected $description = 'Manage permission groups';

    public function __construct(
        protected PermissionGroupService $groupService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action') ?? select(
            label: 'What would you like to do?',
            options: [
                'list' => 'List all groups',
                'create' => 'Create a new group',
                'show' => 'Show group details',
                'sync' => 'Sync permissions to a group',
                'delete' => 'Delete a group',
            ]
        );

        return match ($action) {
            'list' => $this->listGroups(),
            'create' => $this->createGroup(),
            'show' => $this->showGroup(),
            'sync' => $this->syncGroup(),
            'delete' => $this->deleteGroup(),
            default => $this->handleUnknownAction($action),
        };
    }

    protected function handleUnknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}");

        return 1;
    }

    protected function listGroups(): int
    {
        $groups = PermissionGroup::query()
            ->with('parent', 'permissions')
            ->orderBy('sort_order')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No permission groups found.');

            return 0;
        }

        $rows = $groups->map(fn (PermissionGroup $group) => [
            $group->slug,
            $group->name,
            $group->parent->name ?? '-',
            (string) $group->permissions->count(),
            $group->is_system ? 'Yes' : 'No',
        ])->toArray();

        table(
            headers: ['Slug', 'Name', 'Parent', 'Permissions', 'System'],
            rows: $rows
        );

        return 0;
    }

    protected function createGroup(): int
    {
        $name = text(
            label: 'Group name',
            required: true
        );

        $description = text(
            label: 'Description (optional)',
            required: false
        );

        $parentGroups = PermissionGroup::query()
            ->whereNull('parent_id')
            ->pluck('name', 'id')
            ->toArray();

        $parentId = null;
        if (! empty($parentGroups)) {
            $parentOptions = array_merge(['' => 'None (root group)'], $parentGroups);
            $parentId = select(
                label: 'Parent group',
                options: $parentOptions,
                default: ''
            );
            $parentId = $parentId === '' ? null : $parentId;
        }

        $permissions = Permission::query()->pluck('name', 'id')->toArray();
        $selectedPermissions = [];

        if (! empty($permissions) && confirm('Would you like to add permissions?', default: true)) {
            $selectedPermissions = multiselect(
                label: 'Select permissions',
                options: $permissions
            );
        }

        $group = $this->groupService->createGroup(
            name: $name,
            description: $description ?: null,
            parentId: $parentId,
            authz: $selectedPermissions,
        );

        $this->info("Permission group '{$group->name}' created successfully.");

        return 0;
    }

    protected function showGroup(): int
    {
        $slug = $this->option('group') ?? $this->selectGroup('Select a group to view');

        if ($slug === null) {
            return 1;
        }

        $group = $this->groupService->findBySlug($slug);

        if ($group === null) {
            $this->error("Group not found: {$slug}");

            return 1;
        }

        $this->info("Group: {$group->name}");
        $this->line("Slug: {$group->slug}");
        $this->line('Description: '.($group->description ?? 'N/A'));
        $this->line('Parent: '.($group->parent->name ?? 'None'));
        $this->line('System: '.($group->is_system ? 'Yes' : 'No'));
        $this->line("Sort Order: {$group->sort_order}");

        $this->newLine();
        $this->info('Direct Permissions:');

        $permissions = $group->permissions;
        if ($permissions->isEmpty()) {
            $this->line('  No permissions assigned.');
        } else {
            foreach ($permissions as $permission) {
                $this->line("  - {$permission->name}");
            }
        }

        $children = $group->children;
        if ($children->isNotEmpty()) {
            $this->newLine();
            $this->info('Child Groups:');
            foreach ($children as $child) {
                $this->line("  - {$child->name} ({$child->slug})");
            }
        }

        return 0;
    }

    protected function syncGroup(): int
    {
        $slug = $this->option('group') ?? $this->selectGroup('Select a group to sync');

        if ($slug === null) {
            return 1;
        }

        $group = $this->groupService->findBySlug($slug);

        if ($group === null) {
            $this->error("Group not found: {$slug}");

            return 1;
        }

        $permissions = Permission::query()->pluck('name', 'name')->toArray();

        if (empty($permissions)) {
            $this->warn('No permissions available in the database.');

            return 0;
        }

        $currentPermissions = $group->permissions->pluck('name')->toArray();

        $selectedPermissions = multiselect(
            label: "Select permissions for '{$group->name}'",
            options: $permissions,
            default: $currentPermissions
        );

        $this->groupService->syncPermissions($group, $selectedPermissions);

        $this->info("Permissions synced to group '{$group->name}'.");

        return 0;
    }

    protected function deleteGroup(): int
    {
        $slug = $this->option('group') ?? $this->selectGroup('Select a group to delete');

        if ($slug === null) {
            return 1;
        }

        $group = $this->groupService->findBySlug($slug);

        if ($group === null) {
            $this->error("Group not found: {$slug}");

            return 1;
        }

        if ($group->is_system) {
            $this->error('Cannot delete a system group.');

            return 1;
        }

        $childCount = $group->children()->count();
        $permissionCount = $group->permissions()->count();

        $message = "Delete group '{$group->name}'?";
        if ($childCount > 0 || $permissionCount > 0) {
            $message .= " ({$childCount} children, {$permissionCount} permissions will be affected)";
        }

        if (! confirm($message, default: false)) {
            $this->info('Deletion cancelled.');

            return 0;
        }

        $this->groupService->deleteGroup($group);

        $this->info("Group '{$group->name}' deleted successfully.");

        return 0;
    }

    protected function selectGroup(string $label): ?string
    {
        $groups = PermissionGroup::query()
            ->pluck('name', 'slug')
            ->toArray();

        if (empty($groups)) {
            $this->warn('No permission groups found.');

            return null;
        }

        return select(
            label: $label,
            options: $groups
        );
    }
}
