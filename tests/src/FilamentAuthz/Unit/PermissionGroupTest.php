<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Models\Permission;

beforeEach(function (): void {
    // Drop and recreate permission group tables
    Schema::dropIfExists('authz_permission_group_permission');
    Schema::dropIfExists('authz_permission_groups');

    Schema::create('authz_permission_groups', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug');
        $table->text('description')->nullable();
        $table->uuid('parent_id')->nullable();
        $table->json('implicit_abilities')->nullable();
        $table->integer('sort_order')->default(0);
        $table->boolean('is_system')->default(false);
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->timestamps();
    });

    Schema::create('authz_permission_group_permission', function ($table): void {
        $table->uuid('permission_group_id');
        $table->unsignedBigInteger('permission_id');
        $table->timestamps();
    });

    // Create test permissions
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
});

afterEach(function (): void {
    Schema::dropIfExists('authz_permission_group_permission');
    Schema::dropIfExists('authz_permission_groups');
});

test('can create permission group', function (): void {
    $group = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'description' => 'Permissions for user management',
    ]);

    expect($group)->toBeInstanceOf(PermissionGroup::class)
        ->and($group->name)->toBe('User Management')
        ->and($group->slug)->toBe('user-management');
});

test('can attach permissions to group', function (): void {
    $group = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
    ]);

    $permissions = Permission::whereIn('name', ['users.view', 'users.create'])->pluck('id');
    $group->permissions()->attach($permissions);

    expect($group->permissions)->toHaveCount(2)
        ->and($group->permissions->pluck('name')->toArray())->toContain('users.view')
        ->and($group->permissions->pluck('name')->toArray())->toContain('users.create');
});

test('parent relationship works', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    $child = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent)->not->toBeNull()
        ->and($child->parent->id)->toBe($parent->id);
});

test('children relationship works', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
        'sort_order' => 1,
    ]);

    PermissionGroup::create([
        'name' => 'Order Management',
        'slug' => 'order-management',
        'parent_id' => $parent->id,
        'sort_order' => 2,
    ]);

    expect($parent->children)->toHaveCount(2);
});

test('children are ordered by sort_order', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    PermissionGroup::create([
        'name' => 'Second',
        'slug' => 'second',
        'parent_id' => $parent->id,
        'sort_order' => 2,
    ]);

    PermissionGroup::create([
        'name' => 'First',
        'slug' => 'first',
        'parent_id' => $parent->id,
        'sort_order' => 1,
    ]);

    $children = $parent->children;

    expect($children->first()->name)->toBe('First')
        ->and($children->last()->name)->toBe('Second');
});

test('getAllPermissions returns own permissions', function (): void {
    $group = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
    ]);

    $permissions = Permission::whereIn('name', ['users.view', 'users.create'])->pluck('id');
    $group->permissions()->attach($permissions);

    $allPermissions = $group->getAllPermissions();

    expect($allPermissions)->toHaveCount(2)
        ->and($allPermissions->pluck('name')->toArray())->toContain('users.view')
        ->and($allPermissions->pluck('name')->toArray())->toContain('users.create');
});

test('getAllPermissions includes parent permissions', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    $parentPerms = Permission::whereIn('name', ['users.view'])->pluck('id');
    $parent->permissions()->attach($parentPerms);

    $child = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    $childPerms = Permission::whereIn('name', ['users.create'])->pluck('id');
    $child->permissions()->attach($childPerms);

    $allPermissions = $child->getAllPermissions();

    expect($allPermissions)->toHaveCount(2)
        ->and($allPermissions->pluck('name')->toArray())->toContain('users.view')
        ->and($allPermissions->pluck('name')->toArray())->toContain('users.create');
});

test('getAncestors returns all ancestor groups', function (): void {
    $grandparent = PermissionGroup::create([
        'name' => 'All',
        'slug' => 'all',
    ]);

    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
        'parent_id' => $grandparent->id,
    ]);

    $child = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    $ancestors = $child->getAncestors();

    expect($ancestors)->toHaveCount(2)
        ->and($ancestors->pluck('id')->toArray())->toContain($parent->id)
        ->and($ancestors->pluck('id')->toArray())->toContain($grandparent->id);
});

test('getAncestors returns empty for root group', function (): void {
    $root = PermissionGroup::create([
        'name' => 'Root',
        'slug' => 'root',
    ]);

    $ancestors = $root->getAncestors();

    expect($ancestors)->toBeEmpty();
});

test('getDescendants returns all descendant groups', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    $child1 = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    $grandchild = PermissionGroup::create([
        'name' => 'User Roles',
        'slug' => 'user-roles',
        'parent_id' => $child1->id,
    ]);

    $descendants = $parent->getDescendants();

    expect($descendants)->toHaveCount(2)
        ->and($descendants->pluck('id')->toArray())->toContain($child1->id)
        ->and($descendants->pluck('id')->toArray())->toContain($grandchild->id);
});

test('isAncestorOf returns true for ancestor', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    $child = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    expect($parent->isAncestorOf($child))->toBeTrue();
});

test('isAncestorOf returns false for non-ancestor', function (): void {
    $group1 = PermissionGroup::create([
        'name' => 'Group 1',
        'slug' => 'group-1',
    ]);

    $group2 = PermissionGroup::create([
        'name' => 'Group 2',
        'slug' => 'group-2',
    ]);

    expect($group1->isAncestorOf($group2))->toBeFalse();
});

test('isDescendantOf returns true for descendant', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Management',
        'slug' => 'management',
    ]);

    $child = PermissionGroup::create([
        'name' => 'User Management',
        'slug' => 'user-management',
        'parent_id' => $parent->id,
    ]);

    expect($child->isDescendantOf($parent))->toBeTrue();
});

test('isDescendantOf returns false for non-descendant', function (): void {
    $group1 = PermissionGroup::create([
        'name' => 'Group 1',
        'slug' => 'group-1',
    ]);

    $group2 = PermissionGroup::create([
        'name' => 'Group 2',
        'slug' => 'group-2',
    ]);

    expect($group1->isDescendantOf($group2))->toBeFalse();
});

test('getDepth returns correct depth', function (): void {
    $root = PermissionGroup::create([
        'name' => 'Root',
        'slug' => 'root',
    ]);

    $child = PermissionGroup::create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $root->id,
    ]);

    $grandchild = PermissionGroup::create([
        'name' => 'Grandchild',
        'slug' => 'grandchild',
        'parent_id' => $child->id,
    ]);

    expect($root->getDepth())->toBe(0)
        ->and($child->getDepth())->toBe(1)
        ->and($grandchild->getDepth())->toBe(2);
});

test('isRoot returns true for root group', function (): void {
    $root = PermissionGroup::create([
        'name' => 'Root',
        'slug' => 'root',
    ]);

    expect($root->isRoot())->toBeTrue();
});

test('isRoot returns false for child group', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Parent',
        'slug' => 'parent',
    ]);

    $child = PermissionGroup::create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    expect($child->isRoot())->toBeFalse();
});

test('isLeaf returns true for group without children', function (): void {
    $leaf = PermissionGroup::create([
        'name' => 'Leaf',
        'slug' => 'leaf',
    ]);

    expect($leaf->isLeaf())->toBeTrue();
});

test('isLeaf returns false for group with children', function (): void {
    $parent = PermissionGroup::create([
        'name' => 'Parent',
        'slug' => 'parent',
    ]);

    PermissionGroup::create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    expect($parent->isLeaf())->toBeFalse();
});

test('deleting group reassigns children to parent', function (): void {
    $grandparent = PermissionGroup::create([
        'name' => 'Grandparent',
        'slug' => 'grandparent',
    ]);

    $parent = PermissionGroup::create([
        'name' => 'Parent',
        'slug' => 'parent',
        'parent_id' => $grandparent->id,
    ]);

    $child = PermissionGroup::create([
        'name' => 'Child',
        'slug' => 'child',
        'parent_id' => $parent->id,
    ]);

    $parent->delete();

    $child->refresh();

    expect($child->parent_id)->toBe($grandparent->id);
});

test('deleting group detaches permissions', function (): void {
    $group = PermissionGroup::create([
        'name' => 'Test Group',
        'slug' => 'test-group',
    ]);

    $permissions = Permission::whereIn('name', ['users.view', 'users.create'])->pluck('id');
    $group->permissions()->attach($permissions);

    $groupId = $group->id;

    $group->delete();

    $pivotCount = DB::table('authz_permission_group_permission')
        ->where('permission_group_id', $groupId)
        ->count();

    expect($pivotCount)->toBe(0);
});

test('implicit_abilities is cast to array', function (): void {
    $group = PermissionGroup::create([
        'name' => 'Test Group',
        'slug' => 'test-group',
        'implicit_abilities' => ['viewAny', 'view'],
    ]);

    expect($group->implicit_abilities)->toBeArray()
        ->and($group->implicit_abilities)->toContain('viewAny')
        ->and($group->implicit_abilities)->toContain('view');
});

test('is_system is cast to boolean', function (): void {
    $group = PermissionGroup::create([
        'name' => 'System Group',
        'slug' => 'system-group',
        'is_system' => true,
    ]);

    expect($group->is_system)->toBeBool()
        ->and($group->is_system)->toBeTrue();
});

test('sort_order is cast to integer', function (): void {
    $group = PermissionGroup::create([
        'name' => 'Test Group',
        'slug' => 'test-group',
        'sort_order' => '5',
    ]);

    expect($group->sort_order)->toBeInt()
        ->and($group->sort_order)->toBe(5);
});

test('scopeForOwner filters by owner when enabled', function (): void {
    config(['filament-authz.owner.enabled' => true]);

    $group1 = PermissionGroup::create([
        'name' => 'Global Group',
        'slug' => 'global-group',
    ]);

    // Create a test owner user
    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => bcrypt('password'),
    ]);

    $group2 = PermissionGroup::create([
        'name' => 'Owned Group',
        'slug' => 'owned-group',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]);

    $results = PermissionGroup::forOwner($owner)->get();

    expect($results)->toHaveCount(2) // Owned + global
        ->and($results->pluck('slug')->toArray())->toContain('owned-group')
        ->and($results->pluck('slug')->toArray())->toContain('global-group');
});

test('scopeForOwner returns all when owner disabled', function (): void {
    config(['filament-authz.owner.enabled' => false]);

    PermissionGroup::create([
        'name' => 'Group 1',
        'slug' => 'group-1',
    ]);

    PermissionGroup::create([
        'name' => 'Group 2',
        'slug' => 'group-2',
    ]);

    $results = PermissionGroup::forOwner(null)->get();

    expect($results)->toHaveCount(2);
});
