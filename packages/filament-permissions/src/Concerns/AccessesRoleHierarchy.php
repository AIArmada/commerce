<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Concerns;

use Spatie\Permission\Models\Role;

/**
 * Helper trait for accessing extended Role properties added by filament-permissions.
 */
trait AccessesRoleHierarchy
{
    /**
     * Get the parent_role_id from a Role.
     */
    protected function getRoleParentId(Role $role): ?string
    {
        /** @var string|null $parentId */
        $parentId = $role->getAttribute('parent_role_id');

        return $parentId;
    }

    /**
     * Set the parent_role_id on a Role.
     */
    protected function setRoleParentId(Role $role, ?string $parentId): void
    {
        $role->setAttribute('parent_role_id', $parentId);
    }

    /**
     * Get the level from a Role.
     */
    protected function getRoleLevel(Role $role): int
    {
        /** @var int $level */
        $level = $role->getAttribute('level') ?? 0;

        return $level;
    }

    /**
     * Set the level on a Role.
     */
    protected function setRoleLevel(Role $role, int $level): void
    {
        $role->setAttribute('level', $level);
    }

    /**
     * Check if a Role is a system role.
     */
    protected function isSystemRole(Role $role): bool
    {
        /** @var bool $isSystem */
        $isSystem = $role->getAttribute('is_system') ?? false;

        return $isSystem;
    }

    /**
     * Get the template_id from a Role.
     */
    protected function getRoleTemplateId(Role $role): ?string
    {
        /** @var string|null $templateId */
        $templateId = $role->getAttribute('template_id');

        return $templateId;
    }

    /**
     * Set the template_id on a Role.
     */
    protected function setRoleTemplateId(Role $role, ?string $templateId): void
    {
        $role->setAttribute('template_id', $templateId);
    }

    /**
     * Get role metadata.
     *
     * @return array<string, mixed>|null
     */
    protected function getRoleMetadata(Role $role): ?array
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = $role->getAttribute('metadata');

        return $metadata;
    }

    /**
     * Check if a Role is assignable.
     */
    protected function isRoleAssignable(Role $role): bool
    {
        /** @var bool $isAssignable */
        $isAssignable = $role->getAttribute('is_assignable') ?? true;

        return $isAssignable;
    }

    /**
     * Get role description.
     */
    protected function getRoleDescription(Role $role): ?string
    {
        /** @var string|null $description */
        $description = $role->getAttribute('description');

        return $description;
    }
}
