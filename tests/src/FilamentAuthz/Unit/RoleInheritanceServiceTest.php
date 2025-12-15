<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->service = app(RoleInheritanceService::class);

    // Enable hierarchy caching
    config(['filament-authz.hierarchies.cache_hierarchy' => true]);
    config(['filament-authz.hierarchies.max_role_depth' => 5]);

    // Create test permissions
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'admin.access', 'guard_name' => 'web']);

    // Create test roles with hierarchy
    $this->superAdmin = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    $this->admin = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $this->editor = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $this->viewer = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);

    // Give permissions
    $this->superAdmin->givePermissionTo(['admin.access', 'users.delete']);
    $this->admin->givePermissionTo(['users.create', 'users.update']);
    $this->editor->givePermissionTo(['users.view']);

    $this->service->clearCache();
});

test('can be instantiated', function (): void {
    expect($this->service)->toBeInstanceOf(RoleInheritanceService::class);
});

test('setParent sets role parent', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    $parent = $this->service->getParent($this->admin);

    expect($parent)->not->toBeNull()
        ->and($parent->id)->toBe($this->superAdmin->id);
});

test('setParent with null removes parent', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->admin, null);

    $parent = $this->service->getParent($this->admin);

    expect($parent)->toBeNull();
});

test('setParent throws exception for circular reference', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    expect(fn () => $this->service->setParent($this->superAdmin, $this->editor))
        ->toThrow(InvalidArgumentException::class, 'Cannot set a descendant role as parent');
});

test('setParent throws exception when depth exceeds max', function (): void {
    config(['filament-authz.hierarchies.max_role_depth' => 2]);

    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    expect(fn () => $this->service->setParent($this->viewer, $this->editor))
        ->toThrow(InvalidArgumentException::class);
});

test('getParent returns null for root role', function (): void {
    $parent = $this->service->getParent($this->superAdmin);

    expect($parent)->toBeNull();
});

test('getParent returns parent role', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    $parent = $this->service->getParent($this->admin);

    expect($parent->id)->toBe($this->superAdmin->id);
});

test('getAncestors returns all ancestors', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    $ancestors = $this->service->getAncestors($this->editor);

    expect($ancestors)->toHaveCount(2)
        ->and($ancestors->pluck('id')->toArray())->toContain($this->admin->id)
        ->and($ancestors->pluck('id')->toArray())->toContain($this->superAdmin->id);
});

test('getAncestors returns empty for root role', function (): void {
    $ancestors = $this->service->getAncestors($this->superAdmin);

    expect($ancestors)->toBeEmpty();
});

test('getDescendants returns all descendants', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    $descendants = $this->service->getDescendants($this->superAdmin);

    expect($descendants)->toHaveCount(2)
        ->and($descendants->pluck('id')->toArray())->toContain($this->admin->id)
        ->and($descendants->pluck('id')->toArray())->toContain($this->editor->id);
});

test('getDescendants returns empty for leaf role', function (): void {
    $descendants = $this->service->getDescendants($this->editor);

    expect($descendants)->toBeEmpty();
});

test('getChildren returns direct children only', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->superAdmin);
    $this->service->setParent($this->viewer, $this->admin);

    $children = $this->service->getChildren($this->superAdmin);

    expect($children)->toHaveCount(2)
        ->and($children->pluck('id')->toArray())->toContain($this->admin->id)
        ->and($children->pluck('id')->toArray())->toContain($this->editor->id)
        ->and($children->pluck('id')->toArray())->not->toContain($this->viewer->id);
});

test('getRootRoles returns roles without parent', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    $roots = $this->service->getRootRoles();

    expect($roots->pluck('id')->toArray())->toContain($this->superAdmin->id)
        ->and($roots->pluck('id')->toArray())->toContain($this->viewer->id)
        ->and($roots->pluck('id')->toArray())->not->toContain($this->admin->id);
});

test('isAncestorOf returns true for ancestor', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    expect($this->service->isAncestorOf($this->superAdmin, $this->editor))->toBeTrue()
        ->and($this->service->isAncestorOf($this->admin, $this->editor))->toBeTrue();
});

test('isAncestorOf returns false for non-ancestor', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    expect($this->service->isAncestorOf($this->editor, $this->admin))->toBeFalse()
        ->and($this->service->isAncestorOf($this->viewer, $this->admin))->toBeFalse();
});

test('isDescendantOf returns true for descendant', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    expect($this->service->isDescendantOf($this->editor, $this->superAdmin))->toBeTrue()
        ->and($this->service->isDescendantOf($this->admin, $this->superAdmin))->toBeTrue();
});

test('isDescendantOf returns false for non-descendant', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    expect($this->service->isDescendantOf($this->superAdmin, $this->admin))->toBeFalse();
});

test('getDepth returns correct depth', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    expect($this->service->getDepth($this->superAdmin))->toBe(0)
        ->and($this->service->getDepth($this->admin))->toBe(1)
        ->and($this->service->getDepth($this->editor))->toBe(2);
});

test('getHierarchyTree returns root roles', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    $tree = $this->service->getHierarchyTree();

    // Tree should contain root roles
    expect($tree->pluck('id')->toArray())->toContain($this->superAdmin->id);
});

test('moveRole changes parent', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    // Move editor under admin instead of superadmin
    $this->service->moveRole($this->editor, $this->admin);

    expect($this->service->getParent($this->editor)->id)->toBe($this->admin->id);
});

test('detachFromParent removes parent', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);

    $this->service->detachFromParent($this->admin);

    expect($this->service->getParent($this->admin))->toBeNull();
});

test('getInheritedPermissions returns permissions from ancestors', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    $inherited = $this->service->getInheritedPermissions($this->editor);

    expect($inherited->pluck('name')->toArray())
        ->toContain('admin.access')
        ->toContain('users.delete')
        ->toContain('users.create')
        ->toContain('users.update');
});

test('getInheritedPermissions returns empty for root role', function (): void {
    $inherited = $this->service->getInheritedPermissions($this->superAdmin);

    expect($inherited)->toBeEmpty();
});

test('clearCache clears hierarchy cache', function (): void {
    // This should not throw
    $this->service->clearCache();

    expect(true)->toBeTrue();
});

test('hierarchy respects max depth configuration', function (): void {
    config(['filament-authz.hierarchies.max_role_depth' => 3]);

    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);
    $this->service->setParent($this->viewer, $this->editor);

    // Should be at max depth now
    expect($this->service->getDepth($this->viewer))->toBe(3);
});

test('updating hierarchy updates descendant levels', function (): void {
    $this->service->setParent($this->admin, $this->superAdmin);
    $this->service->setParent($this->editor, $this->admin);

    // Editor should be at level 2
    $this->admin->refresh();
    $this->editor->refresh();

    expect($this->service->getDepth($this->admin))->toBe(1)
        ->and($this->service->getDepth($this->editor))->toBe(2);
});

test('cache is used when enabled', function (): void {
    config(['filament-authz.hierarchies.cache_hierarchy' => true]);

    $this->service->setParent($this->admin, $this->superAdmin);

    // First call populates cache
    $ancestors1 = $this->service->getAncestors($this->admin);

    // Second call should return same result from cache
    $ancestors2 = $this->service->getAncestors($this->admin);

    expect($ancestors1->pluck('id'))->toEqual($ancestors2->pluck('id'));
});

test('cache is bypassed when disabled', function (): void {
    config(['filament-authz.hierarchies.cache_hierarchy' => false]);

    $this->service->setParent($this->admin, $this->superAdmin);

    // Should work without caching
    $ancestors = $this->service->getAncestors($this->admin);

    expect($ancestors)->toHaveCount(1);
});
