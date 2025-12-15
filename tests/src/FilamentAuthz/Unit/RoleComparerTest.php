<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\RoleComparer;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleInheritance = Mockery::mock(RoleInheritanceService::class);
    $this->comparer = new RoleComparer($this->roleInheritance);
});

describe('RoleComparer', function (): void {
    describe('compare', function (): void {
        it('compares two roles with same permissions', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo($permission);
            $roleB->givePermissionTo($permission);

            $result = $this->comparer->compare($roleA, $roleB);

            expect($result['role_a'])->toBe('role-a')
                ->and($result['role_b'])->toBe('role-b')
                ->and($result['shared_permissions'])->toBe(['posts.view'])
                ->and($result['only_in_a'])->toBeEmpty()
                ->and($result['only_in_b'])->toBeEmpty()
                ->and($result['similarity_percent'])->toBe(100.0);
        });

        it('compares two roles with different permissions', function (): void {
            $permA = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $permB = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo($permA);
            $roleB->givePermissionTo($permB);

            $result = $this->comparer->compare($roleA, $roleB);

            expect($result['shared_permissions'])->toBeEmpty()
                ->and($result['only_in_a'])->toBe(['posts.view'])
                ->and($result['only_in_b'])->toBe(['posts.edit'])
                ->and($result['similarity_percent'])->toBe(0.0);
        });

        it('compares two roles with partial overlap', function (): void {
            $shared = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $onlyA = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $onlyB = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo([$shared, $onlyA]);
            $roleB->givePermissionTo([$shared, $onlyB]);

            $result = $this->comparer->compare($roleA, $roleB);

            expect($result['shared_permissions'])->toBe(['posts.view'])
                ->and($result['only_in_a'])->toBe(['posts.edit'])
                ->and($result['only_in_b'])->toBe(['posts.delete'])
                ->and($result['similarity_percent'])->toBe(33.33);
        });

        it('handles roles with no permissions', function (): void {
            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $result = $this->comparer->compare($roleA, $roleB);

            expect($result['shared_permissions'])->toBeEmpty()
                ->and($result['only_in_a'])->toBeEmpty()
                ->and($result['only_in_b'])->toBeEmpty()
                ->and($result['similarity_percent'])->toBe(100.0);
        });
    });

    describe('compareWithParent', function (): void {
        it('returns null when no parent', function (): void {
            $role = Role::create(['name' => 'role', 'guard_name' => 'web']);

            $this->roleInheritance->shouldReceive('getParent')
                ->with($role)
                ->andReturn(null);

            $result = $this->comparer->compareWithParent($role);

            expect($result)->toBeNull();
        });

        it('returns comparison with parent', function (): void {
            $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
            $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

            $parentPerm = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $childPerm = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $parent->givePermissionTo($parentPerm);
            $child->givePermissionTo([$parentPerm, $childPerm]);

            $this->roleInheritance->shouldReceive('getParent')
                ->with($child)
                ->andReturn($parent);

            $result = $this->comparer->compareWithParent($child);

            expect($result['role'])->toBe('child')
                ->and($result['parent'])->toBe('parent')
                ->and($result['inherited_permissions'])->toBe(['posts.view'])
                ->and($result['own_permissions'])->toBe(['posts.edit'])
                ->and($result['override_count'])->toBe(1);
        });
    });

    describe('findSimilarRoles', function (): void {
        it('finds roles with high similarity', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);
            $roleC = Role::create(['name' => 'role-c', 'guard_name' => 'web']);

            $roleA->givePermissionTo($permission);
            $roleB->givePermissionTo($permission);

            $result = $this->comparer->findSimilarRoles($roleA, 50.0);

            expect($result)->toHaveCount(1)
                ->and($result[0]['role'])->toBe('role-b')
                ->and($result[0]['similarity_percent'])->toBe(100.0);
        });

        it('filters out roles below threshold', function (): void {
            $permA = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $permB = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo($permA);
            $roleB->givePermissionTo($permB);

            $result = $this->comparer->findSimilarRoles($roleA, 50.0);

            expect($result)->toBeEmpty();
        });

        it('sorts results by similarity descending', function (): void {
            $perm1 = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $perm2 = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);
            $roleC = Role::create(['name' => 'role-c', 'guard_name' => 'web']);

            $roleA->givePermissionTo([$perm1, $perm2]);
            $roleB->givePermissionTo($perm1);
            $roleC->givePermissionTo([$perm1, $perm2]);

            $result = $this->comparer->findSimilarRoles($roleA, 0.0);

            expect($result)->toHaveCount(2)
                ->and($result[0]['role'])->toBe('role-c')
                ->and($result[0]['similarity_percent'])->toBe(100.0);
        });
    });

    describe('findRedundantRoles', function (): void {
        it('finds roles with identical permissions', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo($permission);
            $roleB->givePermissionTo($permission);

            $result = $this->comparer->findRedundantRoles();

            // Only one group with redundant roles (role-a and role-b have same permissions)
            expect($result)->toHaveCount(1);

            // Find the group with role-a and role-b
            $redundantGroup = collect($result)->first(function ($set) {
                return in_array('role-a', $set['roles']) && in_array('role-b', $set['roles']);
            });

            expect($redundantGroup)->not->toBeNull()
                ->and($redundantGroup['permissions_count'])->toBe(1);
        });

        it('returns empty when no redundant roles', function (): void {
            $perm1 = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $perm2 = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            $roleA->givePermissionTo($perm1);
            $roleB->givePermissionTo($perm2);

            $result = $this->comparer->findRedundantRoles();

            expect($result)->toBeEmpty();
        });
    });

    describe('getDiff', function (): void {
        it('gets diff between two roles', function (): void {
            $perm1 = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $perm2 = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $perm3 = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

            $from = Role::create(['name' => 'from', 'guard_name' => 'web']);
            $to = Role::create(['name' => 'to', 'guard_name' => 'web']);

            $from->givePermissionTo([$perm1, $perm2]);
            $to->givePermissionTo([$perm1, $perm3]);

            $result = $this->comparer->getDiff($from, $to);

            expect($result['to_add'])->toBe(['posts.delete'])
                ->and($result['to_remove'])->toBe(['posts.edit'])
                ->and($result['operations_count'])->toBe(2);
        });

        it('returns empty when roles are identical', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $from = Role::create(['name' => 'from', 'guard_name' => 'web']);
            $to = Role::create(['name' => 'to', 'guard_name' => 'web']);

            $from->givePermissionTo($permission);
            $to->givePermissionTo($permission);

            $result = $this->comparer->getDiff($from, $to);

            expect($result['to_add'])->toBeEmpty()
                ->and($result['to_remove'])->toBeEmpty()
                ->and($result['operations_count'])->toBe(0);
        });
    });

    describe('generateHierarchyReport', function (): void {
        it('generates a hierarchy report', function (): void {
            $roleA = Role::create(['name' => 'role-a', 'guard_name' => 'web']);
            $roleB = Role::create(['name' => 'role-b', 'guard_name' => 'web']);

            // Mock expects any Role to receive getDepth
            $this->roleInheritance->shouldReceive('getDepth')
                ->andReturn(0);

            $result = $this->comparer->generateHierarchyReport();

            expect($result['total_roles'])->toBe(2)
                ->and($result['orphan_roles'])->toBeEmpty();
        });
    });

    describe('findUnusedPermissions', function (): void {
        it('finds permissions not assigned to any role', function (): void {
            $usedPerm = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $unusedPerm = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

            $role = Role::create(['name' => 'role', 'guard_name' => 'web']);
            $role->givePermissionTo($usedPerm);

            $result = $this->comparer->findUnusedPermissions();

            expect($result)->toContain('posts.delete')
                ->and($result)->not->toContain('posts.view');
        });

        it('returns empty when all permissions are used', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $role = Role::create(['name' => 'role', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);

            $result = $this->comparer->findUnusedPermissions();

            expect($result)->toBeEmpty();
        });
    });
});
