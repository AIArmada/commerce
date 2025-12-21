<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\AccessesRoleHierarchy;
use AIArmada\FilamentAuthz\Models\Role;

// Create a test class that uses the trait
class TestRoleHierarchyAccessor
{
    use AccessesRoleHierarchy;

    public function testGetRoleParentId(Role $role): ?string
    {
        return $this->getRoleParentId($role);
    }

    public function testSetRoleParentId(Role $role, ?string $parentId): void
    {
        $this->setRoleParentId($role, $parentId);
    }

    public function testGetRoleLevel(Role $role): int
    {
        return $this->getRoleLevel($role);
    }

    public function testSetRoleLevel(Role $role, int $level): void
    {
        $this->setRoleLevel($role, $level);
    }

    public function testIsSystemRole(Role $role): bool
    {
        return $this->isSystemRole($role);
    }

    public function testGetRoleTemplateId(Role $role): ?string
    {
        return $this->getRoleTemplateId($role);
    }

    public function testSetRoleTemplateId(Role $role, ?string $templateId): void
    {
        $this->setRoleTemplateId($role, $templateId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function testGetRoleMetadata(Role $role): ?array
    {
        return $this->getRoleMetadata($role);
    }

    public function testIsRoleAssignable(Role $role): bool
    {
        return $this->isRoleAssignable($role);
    }

    public function testGetRoleDescription(Role $role): ?string
    {
        return $this->getRoleDescription($role);
    }
}

beforeEach(function (): void {
    $this->accessor = new TestRoleHierarchyAccessor;
});

describe('AccessesRoleHierarchy', function (): void {
    it('gets role parent id when set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('parent_role_id', 'parent-uuid-123');

        expect($this->accessor->testGetRoleParentId($role))->toBe('parent-uuid-123');
    });

    it('gets null parent id when not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testGetRoleParentId($role))->toBeNull();
    });

    it('sets role parent id', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $this->accessor->testSetRoleParentId($role, 'new-parent-id');

        expect($role->getAttribute('parent_role_id'))->toBe('new-parent-id');
    });

    it('sets role parent id to null', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('parent_role_id', 'existing-id');
        $this->accessor->testSetRoleParentId($role, null);

        expect($role->getAttribute('parent_role_id'))->toBeNull();
    });

    it('gets role level when set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('level', 5);

        expect($this->accessor->testGetRoleLevel($role))->toBe(5);
    });

    it('gets default role level of 0 when not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testGetRoleLevel($role))->toBe(0);
    });

    it('sets role level', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $this->accessor->testSetRoleLevel($role, 10);

        expect($role->getAttribute('level'))->toBe(10);
    });

    it('identifies system role when is_system is true', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('is_system', true);

        expect($this->accessor->testIsSystemRole($role))->toBeTrue();
    });

    it('identifies non-system role when is_system is false', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('is_system', false);

        expect($this->accessor->testIsSystemRole($role))->toBeFalse();
    });

    it('identifies non-system role when is_system is not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testIsSystemRole($role))->toBeFalse();
    });

    it('gets role template id when set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('template_id', 'template-uuid-456');

        expect($this->accessor->testGetRoleTemplateId($role))->toBe('template-uuid-456');
    });

    it('gets null template id when not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testGetRoleTemplateId($role))->toBeNull();
    });

    it('sets role template id', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $this->accessor->testSetRoleTemplateId($role, 'new-template-id');

        expect($role->getAttribute('template_id'))->toBe('new-template-id');
    });

    it('gets role metadata when set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $metadata = ['key' => 'value', 'nested' => ['data' => true]];
        $role->setAttribute('metadata', $metadata);

        expect($this->accessor->testGetRoleMetadata($role))->toBe($metadata);
    });

    it('gets null metadata when not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testGetRoleMetadata($role))->toBeNull();
    });

    it('identifies assignable role when is_assignable is true', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('is_assignable', true);

        expect($this->accessor->testIsRoleAssignable($role))->toBeTrue();
    });

    it('identifies non-assignable role when is_assignable is false', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('is_assignable', false);

        expect($this->accessor->testIsRoleAssignable($role))->toBeFalse();
    });

    it('defaults to assignable when is_assignable is not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testIsRoleAssignable($role))->toBeTrue();
    });

    it('gets role description when set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);
        $role->setAttribute('description', 'Administrator role with full access');

        expect($this->accessor->testGetRoleDescription($role))->toBe('Administrator role with full access');
    });

    it('gets null description when not set', function (): void {
        $role = new Role(['name' => 'test', 'guard_name' => 'web']);

        expect($this->accessor->testGetRoleDescription($role))->toBeNull();
    });
});
