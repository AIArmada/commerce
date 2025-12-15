<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new PermissionGroupService;
});

describe('PermissionGroupService', function (): void {
    describe('createGroup', function (): void {
        it('creates a group with basic info', function (): void {
            $group = $this->service->createGroup('Test Group', 'A test group');

            expect($group)->toBeInstanceOf(PermissionGroup::class)
                ->and($group->name)->toBe('Test Group')
                ->and($group->slug)->toBe('test-group')
                ->and($group->description)->toBe('A test group');
        });

        it('creates a group with parent', function (): void {
            $parent = PermissionGroup::create([
                'name' => 'Parent',
                'slug' => 'parent',
            ]);

            $child = $this->service->createGroup('Child', null, $parent->id);

            expect($child->parent_id)->toBe($parent->id);
        });

        it('creates a group with permissions', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $group = $this->service->createGroup(
                'Posts Manager',
                null,
                null,
                ['posts.view', 'posts.edit']
            );

            expect($group->permissions)->toHaveCount(2);
        });

        it('creates a group with implicit abilities', function (): void {
            $group = $this->service->createGroup(
                'Manager',
                null,
                null,
                [],
                ['manage' => ['view', 'edit', 'delete']]
            );

            expect($group->implicit_abilities)->toBe(['manage' => ['view', 'edit', 'delete']]);
        });

        it('creates a system group', function (): void {
            $group = $this->service->createGroup(
                'System Group',
                null,
                null,
                [],
                null,
                true
            );

            expect($group->is_system)->toBeTrue();
        });

        it('clears cache after creation', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:groups:hierarchy_tree');

            $this->service->createGroup('Cache Test');
        });
    });

    describe('updateGroup', function (): void {
        it('updates group name and generates slug', function (): void {
            $group = PermissionGroup::create(['name' => 'Old Name', 'slug' => 'old-name']);

            $updated = $this->service->updateGroup($group, ['name' => 'New Name']);

            expect($updated->name)->toBe('New Name')
                ->and($updated->slug)->toBe('new-name');
        });

        it('updates group with custom slug', function (): void {
            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);

            $updated = $this->service->updateGroup($group, [
                'name' => 'New Name',
                'slug' => 'custom-slug',
            ]);

            expect($updated->slug)->toBe('custom-slug');
        });

        it('syncs permissions when provided', function (): void {
            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);

            $updated = $this->service->updateGroup($group, [
                'permissions' => ['posts.view', 'posts.edit'],
            ]);

            expect($updated->permissions)->toHaveCount(2);
        });
    });

    describe('deleteGroup', function (): void {
        it('deletes a group', function (): void {
            $group = PermissionGroup::create(['name' => 'Delete Me', 'slug' => 'delete-me']);
            $id = $group->id;

            $result = $this->service->deleteGroup($group);

            expect($result)->toBeTrue()
                ->and(PermissionGroup::find($id))->toBeNull();
        });
    });

    describe('syncPermissions', function (): void {
        it('syncs permissions to group', function (): void {
            Permission::create(['name' => 'perm1', 'guard_name' => 'web']);
            Permission::create(['name' => 'perm2', 'guard_name' => 'web']);
            Permission::create(['name' => 'perm3', 'guard_name' => 'web']);

            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);

            $this->service->syncPermissions($group, ['perm1', 'perm2']);

            expect($group->fresh()->permissions)->toHaveCount(2);

            // Sync to different set
            $this->service->syncPermissions($group, ['perm3']);

            expect($group->fresh()->permissions)->toHaveCount(1);
        });
    });

    describe('addPermissions', function (): void {
        it('adds permissions without removing existing', function (): void {
            Permission::create(['name' => 'perm1', 'guard_name' => 'web']);
            Permission::create(['name' => 'perm2', 'guard_name' => 'web']);

            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);
            $this->service->syncPermissions($group, ['perm1']);

            $this->service->addPermissions($group, ['perm2']);

            expect($group->fresh()->permissions)->toHaveCount(2);
        });
    });

    describe('removePermissions', function (): void {
        it('removes specific permissions', function (): void {
            Permission::create(['name' => 'perm1', 'guard_name' => 'web']);
            Permission::create(['name' => 'perm2', 'guard_name' => 'web']);

            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);
            $this->service->syncPermissions($group, ['perm1', 'perm2']);

            $this->service->removePermissions($group, ['perm1']);

            $permissions = $group->fresh()->permissions;
            expect($permissions)->toHaveCount(1)
                ->and($permissions->first()->name)->toBe('perm2');
        });
    });

    describe('getGroupPermissions', function (): void {
        it('gets direct permissions', function (): void {
            Permission::create(['name' => 'perm1', 'guard_name' => 'web']);

            $group = PermissionGroup::create(['name' => 'Test', 'slug' => 'test']);
            $this->service->syncPermissions($group, ['perm1']);

            $permissions = $this->service->getGroupPermissions($group, false);

            expect($permissions)->toHaveCount(1);
        });

        it('gets inherited permissions', function (): void {
            Permission::create(['name' => 'parent.perm', 'guard_name' => 'web']);
            Permission::create(['name' => 'child.perm', 'guard_name' => 'web']);

            $parent = PermissionGroup::create(['name' => 'Parent', 'slug' => 'parent']);
            $child = PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

            $this->service->syncPermissions($parent, ['parent.perm']);
            $this->service->syncPermissions($child, ['child.perm']);

            // With inheritance - should include both
            $permissions = $this->service->getGroupPermissions($child, true);
            expect($permissions->pluck('name')->toArray())->toContain('child.perm');
        });
    });

    describe('getRootGroups', function (): void {
        it('returns only groups without parent', function (): void {
            $root1 = PermissionGroup::create(['name' => 'Root 1', 'slug' => 'root-1', 'sort_order' => 1]);
            $root2 = PermissionGroup::create(['name' => 'Root 2', 'slug' => 'root-2', 'sort_order' => 2]);
            PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root1->id]);

            $roots = $this->service->getRootGroups();

            expect($roots)->toHaveCount(2)
                ->and($roots->pluck('name')->toArray())->toBe(['Root 1', 'Root 2']);
        });
    });

    describe('getHierarchyTree', function (): void {
        it('returns full hierarchy with caching', function (): void {
            $root = PermissionGroup::create(['name' => 'Root', 'slug' => 'root']);
            PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);

            $tree = $this->service->getHierarchyTree();

            expect($tree)->toHaveCount(1)
                ->and($tree->first()->children)->toHaveCount(1);
        });
    });

    describe('findBySlug', function (): void {
        it('finds group by slug', function (): void {
            PermissionGroup::create(['name' => 'Test Group', 'slug' => 'test-group']);

            $found = $this->service->findBySlug('test-group');

            expect($found)->not->toBeNull()
                ->and($found->name)->toBe('Test Group');
        });

        it('returns null for non-existent slug', function (): void {
            $found = $this->service->findBySlug('non-existent');

            expect($found)->toBeNull();
        });
    });

    describe('moveGroup', function (): void {
        it('moves group to new parent', function (): void {
            $parent1 = PermissionGroup::create(['name' => 'Parent 1', 'slug' => 'parent-1']);
            $parent2 = PermissionGroup::create(['name' => 'Parent 2', 'slug' => 'parent-2']);
            $child = PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent1->id]);

            $moved = $this->service->moveGroup($child, $parent2->id);

            expect($moved->parent_id)->toBe($parent2->id);
        });

        it('moves group to root', function (): void {
            $parent = PermissionGroup::create(['name' => 'Parent', 'slug' => 'parent']);
            $child = PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

            $moved = $this->service->moveGroup($child, null);

            expect($moved->parent_id)->toBeNull();
        });

        it('prevents circular references', function (): void {
            $parent = PermissionGroup::create(['name' => 'Parent', 'slug' => 'parent']);
            $child = PermissionGroup::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

            expect(fn () => $this->service->moveGroup($parent, $child->id))
                ->toThrow(InvalidArgumentException::class, 'Cannot move a group to one of its descendants');
        });

        it('checks depth limit', function (): void {
            // Create a chain up to depth limit
            config(['filament-authz.hierarchies.max_group_depth' => 3]);

            $level0 = PermissionGroup::create(['name' => 'Level 0', 'slug' => 'level-0']);
            $level1 = PermissionGroup::create(['name' => 'Level 1', 'slug' => 'level-1', 'parent_id' => $level0->id]);
            $level2 = PermissionGroup::create(['name' => 'Level 2', 'slug' => 'level-2', 'parent_id' => $level1->id]);

            // Try to add another level below (would exceed max of 3)
            $floating = PermissionGroup::create(['name' => 'Floating', 'slug' => 'floating']);
            $floatingChild = PermissionGroup::create(['name' => 'Float Child', 'slug' => 'float-child', 'parent_id' => $floating->id]);

            expect(fn () => $this->service->moveGroup($floating, $level2->id))
                ->toThrow(InvalidArgumentException::class, 'exceed the maximum depth');
        });
    });

    describe('reorderGroups', function (): void {
        it('reorders groups by sort order', function (): void {
            $group1 = PermissionGroup::create(['name' => 'Group 1', 'slug' => 'group-1', 'sort_order' => 0]);
            $group2 = PermissionGroup::create(['name' => 'Group 2', 'slug' => 'group-2', 'sort_order' => 1]);
            $group3 = PermissionGroup::create(['name' => 'Group 3', 'slug' => 'group-3', 'sort_order' => 2]);

            $this->service->reorderGroups([
                $group1->id => 2,
                $group2->id => 0,
                $group3->id => 1,
            ]);

            expect($group1->fresh()->sort_order)->toBe(2)
                ->and($group2->fresh()->sort_order)->toBe(0)
                ->and($group3->fresh()->sort_order)->toBe(1);
        });
    });

    describe('getGroupsWithPermission', function (): void {
        it('finds groups containing a permission', function (): void {
            $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

            $group1 = PermissionGroup::create(['name' => 'Group 1', 'slug' => 'group-1']);
            $group2 = PermissionGroup::create(['name' => 'Group 2', 'slug' => 'group-2']);
            PermissionGroup::create(['name' => 'Group 3', 'slug' => 'group-3']);

            $group1->permissions()->attach($permission);
            $group2->permissions()->attach($permission);

            $groups = $this->service->getGroupsWithPermission('posts.view');

            expect($groups)->toHaveCount(2)
                ->and($groups->pluck('name')->toArray())->toContain('Group 1', 'Group 2');
        });

        it('returns empty collection for non-existent permission', function (): void {
            $groups = $this->service->getGroupsWithPermission('non.existent');

            expect($groups)->toBeEmpty();
        });
    });

    describe('clearCache', function (): void {
        it('clears hierarchy tree cache', function (): void {
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:groups:hierarchy_tree');

            $this->service->clearCache();
        });
    });
});
