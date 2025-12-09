<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Models\RoleTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleTemplateService
{
    protected const CACHE_KEY_PREFIX = 'permissions:templates:';

    /**
     * Create a new role template.
     *
     * @param  array<string>  $defaultPermissions
     * @param  array<string, mixed>|null  $metadata
     */
    public function createTemplate(
        string $name,
        string $guardName = 'web',
        ?string $description = null,
        ?string $parentId = null,
        array $defaultPermissions = [],
        ?array $metadata = null,
        bool $isSystem = false
    ): RoleTemplate {
        $template = RoleTemplate::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'parent_id' => $parentId,
            'guard_name' => $guardName,
            'default_permissions' => $defaultPermissions,
            'metadata' => $metadata,
            'is_system' => $isSystem,
            'is_active' => true,
        ]);

        $this->clearCache();

        return $template;
    }

    /**
     * Update a role template.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTemplate(RoleTemplate $template, array $data): RoleTemplate
    {
        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $template->update($data);
        $this->clearCache();

        return $template->refresh();
    }

    /**
     * Delete a role template.
     */
    public function deleteTemplate(RoleTemplate $template): bool
    {
        $result = $template->delete();
        $this->clearCache();

        return (bool) $result;
    }

    /**
     * Create a role from a template.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function createRoleFromTemplate(
        RoleTemplate $template,
        string $roleName,
        array $overrides = []
    ): Role {
        return $template->createRole($roleName, $overrides);
    }

    /**
     * Sync a role with its template.
     */
    public function syncRoleWithTemplate(Role $role): ?Role
    {
        $templateId = $role->template_id ?? null;

        if ($templateId === null) {
            return null;
        }

        $template = RoleTemplate::find($templateId);

        if ($template === null) {
            return null;
        }

        return $template->syncRole($role);
    }

    /**
     * Sync all roles using a specific template.
     *
     * @return array{synced: int, failed: int}
     */
    public function syncAllRolesFromTemplate(RoleTemplate $template): array
    {
        $synced = 0;
        $failed = 0;

        $roles = Role::query()
            ->where('template_id', $template->id)
            ->get();

        foreach ($roles as $role) {
            try {
                $template->syncRole($role);
                $synced++;
            } catch (Throwable $e) {
                $failed++;
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Get all root templates.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getRootTemplates(): Collection
    {
        return RoleTemplate::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the template hierarchy tree.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getHierarchyTree(): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'hierarchy_tree';
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function (): Collection {
            return RoleTemplate::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with('children')
                ->get();
        });
    }

    /**
     * Find a template by slug.
     */
    public function findBySlug(string $slug): ?RoleTemplate
    {
        return RoleTemplate::query()
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Get all active templates.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getActiveTemplates(): Collection
    {
        return RoleTemplate::query()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get templates by guard name.
     *
     * @return Collection<int, RoleTemplate>
     */
    public function getByGuard(string $guardName): Collection
    {
        return RoleTemplate::query()
            ->where('guard_name', $guardName)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get roles created from a template.
     *
     * @return Collection<int, Role>
     */
    public function getRolesFromTemplate(RoleTemplate $template): Collection
    {
        return Role::query()
            ->where('template_id', $template->id)
            ->get();
    }

    /**
     * Clone a template.
     */
    public function cloneTemplate(RoleTemplate $template, string $newName): RoleTemplate
    {
        return $this->createTemplate(
            name: $newName,
            guardName: $template->guard_name,
            description: $template->description,
            parentId: $template->parent_id,
            defaultPermissions: $template->default_permissions ?? [],
            metadata: $template->metadata,
            isSystem: false
        );
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . 'hierarchy_tree');
    }
}
