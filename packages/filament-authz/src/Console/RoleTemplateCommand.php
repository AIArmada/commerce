<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class RoleTemplateCommand extends Command
{
    protected $signature = 'authz:templates
        {action? : The action to perform (list, create, create-role, sync, sync-all, delete)}
        {--template= : The template slug}
        {--role= : The role name}
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Manage role templates';

    public function handle(RoleTemplateService $service): int
    {
        return $this->withOwnerContext(function () use ($service): int {
            $action = $this->argument('action') ?? select(
                label: 'What would you like to do?',
                options: [
                    'list' => 'List all templates',
                    'create' => 'Create a new template',
                    'create-role' => 'Create role from template',
                    'sync' => 'Sync role with template',
                    'sync-all' => 'Sync all roles from template',
                    'delete' => 'Delete a template',
                ]
            );

            return match ($action) {
                'list' => $this->listTemplates($service),
                'create' => $this->createTemplate($service),
                'create-role' => $this->createRoleFromTemplate($service),
                'sync' => $this->syncRole($service),
                'sync-all' => $this->syncAllRoles($service),
                'delete' => $this->deleteTemplate($service),
                default => $this->listTemplates($service),
            };
        });
    }

    protected function listTemplates(RoleTemplateService $service): int
    {
        $templates = $service->getActiveTemplates();

        if ($templates->isEmpty()) {
            warning('No templates found.');

            return self::SUCCESS;
        }

        $rows = $templates->map(fn (RoleTemplate $template): array => [
            $template->name,
            $template->slug,
            $template->guard_name,
            count($template->default_permissions ?? []),
            $template->is_system ? 'Yes' : 'No',
            $service->getRolesFromTemplate($template)->count(),
        ])->toArray();

        table(
            ['Name', 'Slug', 'Guard', 'Permissions', 'System', 'Roles Using'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function createTemplate(RoleTemplateService $service): int
    {
        $name = text(
            label: 'Template name:',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 2 ? 'Name must be at least 2 characters.' : null
        );

        $description = text(
            label: 'Description (optional):',
            placeholder: 'A brief description of this template'
        );

        $guardName = text(
            label: 'Guard name:',
            default: 'web'
        );

        $permissions = multiselect(
            label: 'Select default authz:',
            options: $this->getPermissionOptions(),
            hint: 'You can add more later'
        );

        $isSystem = confirm(
            label: 'Is this a system template?',
            default: false
        );

        $template = $service->createTemplate(
            name: $name,
            guardName: $guardName,
            description: $description ?: null,
            defaultPermissions: $permissions,
            isSystem: $isSystem
        );

        info("Template '{$template->name}' created successfully.");

        return self::SUCCESS;
    }

    protected function createRoleFromTemplate(RoleTemplateService $service): int
    {
        $templateSlug = $this->option('template') ?? $this->searchTemplate('Select a template:');

        if ($templateSlug === null) {
            return self::FAILURE;
        }

        $template = $service->findBySlug($templateSlug);

        if ($template === null) {
            warning("Template '{$templateSlug}' not found.");

            return self::FAILURE;
        }

        $roleName = $this->option('role') ?? text(
            label: 'Role name:',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 2 ? 'Name must be at least 2 characters.' : null
        );

        $role = $service->createRoleFromTemplate($template, $roleName);

        info("Role '{$role->name}' created from template '{$template->name}'.");
        info('Permissions assigned: ' . implode(', ', $template->default_permissions ?? []));

        return self::SUCCESS;
    }

    protected function syncRole(RoleTemplateService $service): int
    {
        $roleName = $this->option('role') ?? text(
            label: 'Role name to sync:',
            required: true
        );

        /** @var Role $role */
        $role = Role::findByName($roleName);

        $synced = $service->syncRoleWithTemplate($role);

        if ($synced === null) {
            warning("Role '{$roleName}' is not linked to any template.");

            return self::FAILURE;
        }

        info("Role '{$roleName}' synced with its template.");

        return self::SUCCESS;
    }

    protected function syncAllRoles(RoleTemplateService $service): int
    {
        $templateSlug = $this->option('template') ?? $this->searchTemplate('Select a template:');

        if ($templateSlug === null) {
            return self::FAILURE;
        }

        $template = $service->findBySlug($templateSlug);

        if ($template === null) {
            warning("Template '{$templateSlug}' not found.");

            return self::FAILURE;
        }

        $result = $service->syncAllRolesFromTemplate($template);

        info("Synced {$result['synced']} roles.");

        if ($result['failed'] > 0) {
            warning("Failed to sync {$result['failed']} roles.");
        }

        return self::SUCCESS;
    }

    protected function deleteTemplate(RoleTemplateService $service): int
    {
        $templateSlug = $this->option('template') ?? $this->searchTemplate('Select a template to delete:');

        if ($templateSlug === null) {
            return self::FAILURE;
        }

        $template = $service->findBySlug($templateSlug);

        if ($template === null) {
            warning("Template '{$templateSlug}' not found.");

            return self::FAILURE;
        }

        $roles = $service->getRolesFromTemplate($template);

        if ($roles->isNotEmpty()) {
            warning("Template is used by {$roles->count()} roles: " . $roles->pluck('name')->implode(', '));

            if (! confirm('Delete anyway?', false)) {
                return self::FAILURE;
            }
        }

        if (confirm("Are you sure you want to delete template '{$template->name}'?", false)) {
            $service->deleteTemplate($template);
            info("Template '{$template->name}' deleted.");

            return self::SUCCESS;
        }

        return self::FAILURE;
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

    protected function searchTemplate(string $label): ?string
    {
        return search(
            label: $label,
            options: fn (string $value): array => RoleTemplate::query()
                ->where('name', 'like', "%{$value}%")
                ->orWhere('slug', 'like', "%{$value}%")
                ->pluck('slug', 'slug')
                ->toArray(),
            placeholder: 'Type to search...'
        );
    }

    /**
     * @return array<string, string>
     */
    protected function getPermissionOptions(): array
    {
        return Permission::query()
            ->pluck('name', 'name')
            ->toArray();
    }
}
