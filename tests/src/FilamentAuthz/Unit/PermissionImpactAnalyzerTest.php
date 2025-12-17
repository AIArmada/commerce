<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Enums\ImpactLevel;
use AIArmada\FilamentAuthz\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleInheritance = Mockery::mock(RoleInheritanceService::class);
    $this->analyzer = new PermissionImpactAnalyzer($this->roleInheritance);
});

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionImpactAnalyzer', function (): void {
    describe('analyzePermissionGrant', function (): void {
        it('analyzes impact of granting a permission', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzePermissionGrant('posts.view', $role);

            expect($result)->toHaveKeys([
                'permission',
                'role',
                'impact_level',
                'affected_users_count',
                'affected_roles',
                'reasoning',
            ])
                ->and($result['permission'])->toBe('posts.view')
                ->and($result['role'])->toBe('editor')
                ->and($result['impact_level'])->toBeInstanceOf(ImpactLevel::class);
        });

        it('includes child roles in affected roles', function (): void {
            $parent = Role::create(['name' => 'manager', 'guard_name' => 'web']);
            $child = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($parent)
                ->andReturn(new Collection([$child]));

            $result = $this->analyzer->analyzePermissionGrant('posts.manage', $parent);

            expect($result['affected_roles'])->toContain('manager', 'editor');
        });
    });

    describe('analyzePermissionRevoke', function (): void {
        it('analyzes impact of revoking a permission', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzePermissionRevoke('posts.view', $role);

            expect($result)->toHaveKeys([
                'permission',
                'role',
                'impact_level',
                'affected_users_count',
                'affected_roles',
                'users_losing_access',
                'reasoning',
            ]);
        });
    });

    describe('analyzeRoleDeletion', function (): void {
        it('analyzes impact of deleting a role', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getChildren')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeRoleDeletion($role);

            expect($result)->toHaveKeys([
                'role',
                'impact_level',
                'affected_users_count',
                'child_roles',
                'permissions_to_redistribute',
                'reasoning',
            ])
                ->and($result['role'])->toBe('editor');
        });

        it('escalates impact when role has children', function (): void {
            $parent = Role::create(['name' => 'manager', 'guard_name' => 'web']);
            $child = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getChildren')
                ->with($parent)
                ->andReturn(new Collection([$child]));

            $result = $this->analyzer->analyzeRoleDeletion($parent);

            expect($result['child_roles'])->toContain('editor');
        });
    });

    describe('analyzeHierarchyChange', function (): void {
        it('analyzes impact of hierarchy change', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $newParent = Role::create(['name' => 'manager', 'guard_name' => 'web']);

            $this->roleInheritance
                ->shouldReceive('getParent')
                ->with($role)
                ->andReturn(null);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeHierarchyChange($role, $newParent);

            expect($result)->toHaveKeys([
                'role',
                'old_parent',
                'new_parent',
                'impact_level',
                'permissions_gained',
                'permissions_lost',
                'affected_users_count',
                'reasoning',
            ])
                ->and($result['role'])->toBe('editor')
                ->and($result['old_parent'])->toBeNull()
                ->and($result['new_parent'])->toBe('manager');
        });

        it('detects permissions gained from new parent', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $newParent = Role::create(['name' => 'manager', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            $newParent->givePermissionTo($permission);

            $this->roleInheritance
                ->shouldReceive('getParent')
                ->with($role)
                ->andReturn(null);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeHierarchyChange($role, $newParent);

            expect($result['permissions_gained'])->toContain('posts.manage');
        });

        it('handles change to null parent', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $oldParent = Role::create(['name' => 'manager', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            $oldParent->givePermissionTo($permission);

            $this->roleInheritance
                ->shouldReceive('getParent')
                ->with($role)
                ->andReturn($oldParent);

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeHierarchyChange($role, null);

            expect($result['permissions_lost'])->toContain('posts.manage')
                ->and($result['new_parent'])->toBeNull();
        });
    });

    describe('analyzeBulkChange', function (): void {
        it('analyzes impact of bulk permission changes', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permissions = ['posts.view', 'posts.edit', 'posts.delete'];

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeBulkChange('grant', $role, $permissions);

            expect($result)->toHaveKeys([
                'operation',
                'role',
                'permission_count',
                'impact_level',
                'affected_users_count',
                'affected_roles',
                'reasoning',
            ])
                ->and($result['operation'])->toBe('grant')
                ->and($result['permission_count'])->toBe(3);
        });

        it('escalates impact for large bulk changes', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permissions = array_map(fn ($i) => "perm.{$i}", range(1, 15));

            $this->roleInheritance
                ->shouldReceive('getDescendants')
                ->with($role)
                ->andReturn(new Collection);

            $result = $this->analyzer->analyzeBulkChange('revoke', $role, $permissions);

            expect($result['permission_count'])->toBe(15);
        });
    });
});
