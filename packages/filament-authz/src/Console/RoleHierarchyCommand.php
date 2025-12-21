<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use AIArmada\FilamentAuthz\Models\Role;

use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class RoleHierarchyCommand extends Command
{
    protected $signature = 'authz:roles-hierarchy
        {action? : The action to perform (list, set-parent, detach, tree)}
        {--role= : The role name}
        {--parent= : The parent role name}
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Manage role hierarchy';

    public function handle(RoleInheritanceService $service): int
    {
        return $this->withOwnerContext(function () use ($service): int {
            $action = $this->argument('action') ?? select(
                label: 'What would you like to do?',
                options: [
                    'list' => 'List role hierarchy',
                    'tree' => 'Show hierarchy tree',
                    'set-parent' => 'Set parent role',
                    'detach' => 'Detach from parent',
                ]
            );

            return match ($action) {
                'list' => $this->listRoles($service),
                'tree' => $this->showTree($service),
                'set-parent' => $this->setParent($service),
                'detach' => $this->detachFromParent($service),
                default => $this->listRoles($service),
            };
        });
    }

    private function withOwnerContext(callable $callback): int
    {
        if (! config('filament-authz.owner.enabled', false)) {
            return (int) $callback();
        }

        if (OwnerContext::resolve() !== null) {
            return (int) $callback();
        }

        $ownerType = $this->option('owner-type');
        $ownerId = $this->option('owner-id');

        if ($ownerType === null || $ownerId === null || $ownerType === '' || $ownerId === '') {
            $this->error('Owner context is required when filament-authz.owner.enabled is true.');
            $this->line('Provide --owner-type and --owner-id, or bind OwnerResolverInterface.');

            return self::FAILURE;
        }

        try {
            $owner = OwnerContext::fromTypeAndId((string) $ownerType, $ownerId);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return (int) OwnerContext::withOwner($owner, $callback);
    }

    protected function listRoles(RoleInheritanceService $service): int
    {
        $roles = Role::query()->orderBy('level')->orderBy('name')->get();

        if ($roles->isEmpty()) {
            warning('No roles found.');

            return self::SUCCESS;
        }

        $rows = $roles->map(function (Role $role) use ($service): array {
            $parent = $service->getParent($role);
            $childCount = $service->getChildren($role)->count();

            return [
                $role->name,
                $role->guard_name,
                /** @phpstan-ignore-next-line property.notFound */
                $role->level ?? 0,
                $parent->name ?? '-',
                (string) $childCount,
                /** @phpstan-ignore-next-line property.notFound */
                $role->is_system ? 'Yes' : 'No',
            ];
        })->toArray();

        table(
            ['Name', 'Guard', 'Level', 'Parent', 'Children', 'System'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function showTree(RoleInheritanceService $service): int
    {
        $rootRoles = $service->getRootRoles();

        if ($rootRoles->isEmpty()) {
            warning('No roles found.');

            return self::SUCCESS;
        }

        info('Role Hierarchy Tree:');
        $this->newLine();

        foreach ($rootRoles as $role) {
            $this->printRoleTree($role, $service);
        }

        return self::SUCCESS;
    }

    protected function printRoleTree(Role $role, RoleInheritanceService $service, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);
        $prefix = $depth > 0 ? '├─ ' : '';
        $permCount = $role->permissions()->count();

        $this->line("{$indent}{$prefix}<info>{$role->name}</info> <comment>({$permCount} permissions)</comment>");

        $children = $service->getChildren($role);
        foreach ($children as $child) {
            $this->printRoleTree($child, $service, $depth + 1);
        }
    }

    protected function setParent(RoleInheritanceService $service): int
    {
        $roleName = $this->option('role') ?? $this->searchRole('Select the role:');

        if ($roleName === null) {
            return self::FAILURE;
        }

        /** @var Role $role */
        $role = Role::findByName($roleName);

        $parentName = $this->option('parent') ?? $this->searchRole('Select the parent role (or leave empty for none):');

        /** @var Role|null $parent */
        $parent = $parentName !== null ? Role::findByName($parentName) : null;

        try {
            $service->setParent($role, $parent);
            info("Parent role set successfully for '{$roleName}'.");

            return self::SUCCESS;
        } catch (InvalidArgumentException $e) {
            warning($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function detachFromParent(RoleInheritanceService $service): int
    {
        $roleName = $this->option('role') ?? $this->searchRole('Select the role to detach:');

        if ($roleName === null) {
            return self::FAILURE;
        }

        /** @var Role $role */
        $role = Role::findByName($roleName);

        /** @phpstan-ignore property.notFound */
        if ($role->parent_role_id === null) {
            warning("Role '{$roleName}' has no parent.");

            return self::FAILURE;
        }

        $service->detachFromParent($role);
        info("Role '{$roleName}' detached from parent.");

        return self::SUCCESS;
    }

    protected function searchRole(string $label): ?string
    {
        return search(
            label: $label,
            options: fn (string $value): array => Role::query()
                ->where('name', 'like', "%{$value}%")
                ->pluck('name', 'name')
                ->toArray(),
            placeholder: 'Type to search...'
        );
    }
}
