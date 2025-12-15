<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->resolver = app(WildcardPermissionResolver::class);

    // Create test permissions
    Permission::create(['name' => 'users.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.viewAny', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $this->resolver->clearCache();
});

test('can be instantiated', function (): void {
    expect($this->resolver)->toBeInstanceOf(WildcardPermissionResolver::class);
});

test('isWildcard returns false for non-wildcard permission', function (): void {
    expect($this->resolver->isWildcard('users.view'))->toBeFalse();
    expect($this->resolver->isWildcard('orders.create'))->toBeFalse();
    expect($this->resolver->isWildcard('simple'))->toBeFalse();
});

test('isWildcard returns true for wildcard permission', function (): void {
    expect($this->resolver->isWildcard('*'))->toBeTrue();
    expect($this->resolver->isWildcard('users.*'))->toBeTrue();
    expect($this->resolver->isWildcard('*.view'))->toBeTrue();
    expect($this->resolver->isWildcard('users.*.view'))->toBeTrue();
});

test('resolve returns single permission for non-wildcard', function (): void {
    $resolved = $this->resolver->resolve('users.view');

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first())->toBe('users.view');
});

test('resolve returns all permissions for universal wildcard', function (): void {
    $resolved = $this->resolver->resolve('*');

    expect($resolved)->toHaveCount(10)
        ->and($resolved)->toContain('users.viewAny')
        ->and($resolved)->toContain('orders.view')
        ->and($resolved)->toContain('products.viewAny');
});

test('resolve returns prefix-matching permissions for prefix wildcard', function (): void {
    $resolved = $this->resolver->resolve('users.*');

    expect($resolved)->toHaveCount(5)
        ->and($resolved)->toContain('users.viewAny')
        ->and($resolved)->toContain('users.view')
        ->and($resolved)->toContain('users.create')
        ->and($resolved)->toContain('users.update')
        ->and($resolved)->toContain('users.delete')
        ->and($resolved)->not->toContain('orders.view');
});

test('resolve returns pattern-matching permissions for pattern wildcard', function (): void {
    $resolved = $this->resolver->resolve('*.view');

    expect($resolved)->toHaveCount(3)
        ->and($resolved)->toContain('users.view')
        ->and($resolved)->toContain('orders.view')
        ->and($resolved)->toContain('products.view')
        ->and($resolved)->not->toContain('users.create');
});

test('matches returns true for exact match', function (): void {
    expect($this->resolver->matches('users.view', 'users.view'))->toBeTrue();
});

test('matches returns true for universal wildcard', function (): void {
    expect($this->resolver->matches('*', 'users.view'))->toBeTrue();
    expect($this->resolver->matches('*', 'any.permission'))->toBeTrue();
});

test('matches returns true for prefix wildcard match', function (): void {
    expect($this->resolver->matches('users.*', 'users.view'))->toBeTrue();
    expect($this->resolver->matches('users.*', 'users.create'))->toBeTrue();
    expect($this->resolver->matches('orders.*', 'orders.view'))->toBeTrue();
});

test('matches returns false for prefix wildcard non-match', function (): void {
    expect($this->resolver->matches('users.*', 'orders.view'))->toBeFalse();
    expect($this->resolver->matches('orders.*', 'users.create'))->toBeFalse();
});

test('matches returns true for pattern wildcard match', function (): void {
    expect($this->resolver->matches('*.view', 'users.view'))->toBeTrue();
    expect($this->resolver->matches('*.view', 'orders.view'))->toBeTrue();
});

test('matches returns false for pattern wildcard non-match', function (): void {
    expect($this->resolver->matches('*.view', 'users.create'))->toBeFalse();
    expect($this->resolver->matches('*.create', 'users.view'))->toBeFalse();
});

test('matches returns false for non-matching permission', function (): void {
    expect($this->resolver->matches('users.view', 'orders.view'))->toBeFalse();
    expect($this->resolver->matches('users.view', 'users.create'))->toBeFalse();
});

test('getPrefixes returns unique permission prefixes', function (): void {
    $prefixes = $this->resolver->getPrefixes();

    expect($prefixes)->toHaveCount(3)
        ->and($prefixes)->toContain('users')
        ->and($prefixes)->toContain('orders')
        ->and($prefixes)->toContain('products');
});

test('getByPrefix returns all permissions with given prefix', function (): void {
    $userPerms = $this->resolver->getByPrefix('users');
    $orderPerms = $this->resolver->getByPrefix('orders');

    expect($userPerms)->toHaveCount(5)
        ->and($userPerms)->toContain('users.viewAny')
        ->and($orderPerms)->toHaveCount(3);
});

test('getByPrefix returns empty collection for non-existent prefix', function (): void {
    $perms = $this->resolver->getByPrefix('nonexistent');

    expect($perms)->toBeEmpty();
});

test('groupByPrefix groups permissions by their prefix', function (): void {
    $grouped = $this->resolver->groupByPrefix();

    expect($grouped)->toHaveKey('users')
        ->and($grouped)->toHaveKey('orders')
        ->and($grouped)->toHaveKey('products')
        ->and($grouped['users'])->toHaveCount(5)
        ->and($grouped['orders'])->toHaveCount(3)
        ->and($grouped['products'])->toHaveCount(2);
});

test('extractPrefix extracts prefix from permission', function (): void {
    expect($this->resolver->extractPrefix('users.view'))->toBe('users')
        ->and($this->resolver->extractPrefix('orders.create'))->toBe('orders')
        ->and($this->resolver->extractPrefix('simple'))->toBeNull();
});

test('extractAction extracts action from permission', function (): void {
    expect($this->resolver->extractAction('users.view'))->toBe('view')
        ->and($this->resolver->extractAction('orders.create'))->toBe('create')
        ->and($this->resolver->extractAction('simple'))->toBeNull();
});

test('buildPermission creates permission string from components', function (): void {
    expect($this->resolver->buildPermission('users', 'view'))->toBe('users.view')
        ->and($this->resolver->buildPermission('orders', 'create'))->toBe('orders.create');
});

test('userHasPermission returns true for direct permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->givePermissionTo('users.view');

    expect($this->resolver->userHasPermission($user, 'users.view'))->toBeTrue();
});

test('userHasPermission returns false for missing permission', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($this->resolver->userHasPermission($user, 'users.view'))->toBeFalse();
});

test('userHasPermission returns true for wildcard permission match', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test3@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::create(['name' => 'users.*', 'guard_name' => 'web']);
    $user->givePermissionTo('users.*');

    expect($this->resolver->userHasPermission($user, 'users.view'))->toBeTrue();
    expect($this->resolver->userHasPermission($user, 'users.create'))->toBeTrue();
});

test('userHasPermission returns false for object without getAllPermissions', function (): void {
    $obj = new stdClass;

    expect($this->resolver->userHasPermission($obj, 'users.view'))->toBeFalse();
});

test('clearCache clears the permission cache', function (): void {
    // First call populates cache
    $this->resolver->resolve('users.*');

    // Clear cache
    $this->resolver->clearCache();

    // Add a new permission
    Permission::create(['name' => 'users.export', 'guard_name' => 'web']);

    // Should see the new permission after cache clear
    $resolved = $this->resolver->resolve('users.*');

    expect($resolved)->toContain('users.export');
});
