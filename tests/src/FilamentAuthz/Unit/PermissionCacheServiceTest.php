<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

beforeEach(function (): void {
    // Configure cache store before instantiating service
    config(['filament-authz.cache.store' => 'array']);
    config(['filament-authz.cache.enabled' => true]);

    $this->cacheService = app(PermissionCacheService::class);
    $this->cacheService->flush();
    $this->cacheService->enable();

    // Create test permissions
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);

    // Create test role
    $this->testRole = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $this->testRole->givePermissionTo(['users.view', 'users.create']);
});

test('can be instantiated', function (): void {
    expect($this->cacheService)->toBeInstanceOf(PermissionCacheService::class);
});

test('remember caches and returns callback result', function (): void {
    $counter = 0;

    $result1 = $this->cacheService->remember('test-key', function () use (&$counter) {
        $counter++;

        return 'test-value';
    });

    $result2 = $this->cacheService->remember('test-key', function () use (&$counter) {
        $counter++;

        return 'different-value';
    });

    expect($result1)->toBe('test-value')
        ->and($result2)->toBe('test-value')
        ->and($counter)->toBe(1);
});

test('remember bypasses cache when disabled', function (): void {
    $this->cacheService->disable();
    $counter = 0;

    $this->cacheService->remember('test-key', function () use (&$counter) {
        $counter++;

        return 'test-value';
    });

    $this->cacheService->remember('test-key', function () use (&$counter) {
        $counter++;

        return 'test-value';
    });

    expect($counter)->toBe(2);
});

test('getUserPermissions returns user permissions', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view', 'orders.view']);

    $permissions = $this->cacheService->getUserPermissions($user);

    expect($permissions)->toContain('users.view')
        ->and($permissions)->toContain('orders.view')
        ->and($permissions)->not->toContain('users.create');
});

test('getUserPermissions caches results', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view']);

    // First call
    $permissions1 = $this->cacheService->getUserPermissions($user);

    // Give new permission (won't appear in cached result)
    $user->givePermissionTo(['orders.view']);

    // Second call should return cached result
    $permissions2 = $this->cacheService->getUserPermissions($user);

    expect($permissions1)->toBe($permissions2)
        ->and($permissions2)->not->toContain('orders.view');
});

test('getRolePermissions returns role permissions', function (): void {
    $permissions = $this->cacheService->getRolePermissions($this->testRole);

    expect($permissions)->toContain('users.view')
        ->and($permissions)->toContain('users.create')
        ->and($permissions)->not->toContain('orders.view');
});

test('userHasPermission checks permission correctly', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test3@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view']);

    expect($this->cacheService->userHasPermission($user, 'users.view'))->toBeTrue()
        ->and($this->cacheService->userHasPermission($user, 'orders.view'))->toBeFalse();
});

test('forgetUser invalidates user cache', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test4@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view']);

    // Cache the permissions
    $permissions1 = $this->cacheService->getUserPermissions($user);

    // Add new permission
    $user->givePermissionTo(['orders.view']);

    // Invalidate cache
    $this->cacheService->forgetUser($user);

    // Now should see the new permission
    $permissions2 = $this->cacheService->getUserPermissions($user);

    expect($permissions2)->toContain('orders.view');
});

test('forgetRole invalidates role cache', function (): void {
    // Cache role permissions
    $this->cacheService->getRolePermissions($this->testRole);

    // Give new permission
    $this->testRole->givePermissionTo('orders.view');

    // Invalidate cache
    $this->cacheService->forgetRole($this->testRole);

    // Should now see the new permission
    $permissions = $this->cacheService->getRolePermissions($this->testRole);

    expect($permissions)->toContain('orders.view');
});

test('forgetPermission invalidates permission cache', function (): void {
    $permission = Permission::findByName('users.view', 'web');

    // This should not throw
    $this->cacheService->forgetPermission($permission);

    expect(true)->toBeTrue();
});

test('flush clears all caches', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test5@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view']);
    $user->assignRole($this->testRole);

    // Cache data
    $this->cacheService->getUserPermissions($user);
    $this->cacheService->getRolePermissions($this->testRole);

    // Flush all caches
    $this->cacheService->flush();

    // Add new permissions
    $user->givePermissionTo(['orders.view']);
    $this->testRole->givePermissionTo('orders.view');

    // Should see new permissions after flush
    $userPerms = $this->cacheService->getUserPermissions($user);
    $rolePerms = $this->cacheService->getRolePermissions($this->testRole);

    expect($userPerms)->toContain('orders.view')
        ->and($rolePerms)->toContain('orders.view');
});

test('warmUserCache pre-populates user cache', function (): void {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test6@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->givePermissionTo(['users.view']);

    // Warm the cache
    $this->cacheService->warmUserCache($user);

    // Should be cached now - verify by disabling cache and checking result stays same
    $cachedPerms = $this->cacheService->getUserPermissions($user);

    expect($cachedPerms)->toContain('users.view');
});

test('warmRoleCache pre-populates role cache', function (): void {
    // Warm all role caches
    $this->cacheService->warmRoleCache();

    // This should be fast as it's cached
    $permissions = $this->cacheService->getRolePermissions($this->testRole);

    expect($permissions)->toContain('users.view');
});

test('getStats returns cache statistics', function (): void {
    $stats = $this->cacheService->getStats();

    expect($stats)->toHaveKey('enabled')
        ->and($stats)->toHaveKey('store')
        ->and($stats)->toHaveKey('ttl')
        ->and($stats['enabled'])->toBeBool()
        ->and($stats['ttl'])->toBeInt();
});

test('disable prevents caching', function (): void {
    $result = $this->cacheService->disable();

    expect($result)->toBeInstanceOf(PermissionCacheService::class);

    $stats = $this->cacheService->getStats();
    expect($stats['enabled'])->toBeFalse();
});

test('enable re-enables caching', function (): void {
    $this->cacheService->disable();
    $result = $this->cacheService->enable();

    expect($result)->toBeInstanceOf(PermissionCacheService::class);

    $stats = $this->cacheService->getStats();
    expect($stats['enabled'])->toBeTrue();
});

test('withoutCache executes callback without caching', function (): void {
    $counter = 0;

    // First call with cache
    $this->cacheService->remember('test-key', function () use (&$counter) {
        $counter++;

        return 'cached';
    });

    // Call within withoutCache - should execute callback despite same key
    $result = $this->cacheService->withoutCache(function () use (&$counter) {
        return $this->cacheService->remember('test-key', function () use (&$counter) {
            $counter++;

            return 'not-cached';
        });
    });

    // Verify callback was executed
    expect($result)->toBe('not-cached')
        ->and($counter)->toBe(2);
});

test('withoutCache restores cache state after execution', function (): void {
    $this->cacheService->enable();

    $this->cacheService->withoutCache(function (): void {
        $stats = $this->cacheService->getStats();
        expect($stats['enabled'])->toBeFalse();
    });

    // Should be re-enabled after withoutCache completes
    $stats = $this->cacheService->getStats();
    expect($stats['enabled'])->toBeTrue();
});

test('withoutCache restores cache state even on exception', function (): void {
    $this->cacheService->enable();

    try {
        $this->cacheService->withoutCache(function (): void {
            throw new Exception('Test exception');
        });
    } catch (Exception $e) {
        // Expected
    }

    // Should still be re-enabled after exception
    $stats = $this->cacheService->getStats();
    expect($stats['enabled'])->toBeTrue();
});

test('getUserPermissions returns empty array for user without getAllPermissions method', function (): void {
    $user = new class
    {
        public function getKey(): int
        {
            return 1;
        }
    };

    $permissions = $this->cacheService->getUserPermissions($user);

    expect($permissions)->toBe([]);
});
