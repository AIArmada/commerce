<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleInheritance = app(RoleInheritanceService::class);
    $this->wildcardResolver = app(WildcardPermissionResolver::class);
    $this->implicitService = app(ImplicitPermissionService::class);

    $this->aggregator = new PermissionAggregator(
        $this->roleInheritance,
        $this->wildcardResolver,
        $this->implicitService
    );
});

describe('PermissionAggregator', function (): void {
    describe('getEffectivePermissions', function (): void {
        it('returns empty collection for objects without getRoleNames', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $permissions = $this->aggregator->getEffectivePermissions($nonUser);

            expect($permissions)->toBeEmpty();
        });
    });

    describe('getEffectiveRolePermissions', function (): void {
        it('gets direct role permissions', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);

            Cache::flush();
            $permissions = $this->aggregator->getEffectiveRolePermissions($role);

            expect($permissions->pluck('name')->toArray())->toContain('posts.edit');
        });

        it('uses caching for role permissions', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            // First call
            $this->aggregator->getEffectiveRolePermissions($role);

            $cacheKey = 'permissions:aggregated:role:' . $role->id;
            expect(Cache::has($cacheKey))->toBeTrue();
        });
    });

    describe('getPermissionSource', function (): void {
        it('identifies direct permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.view');

            $source = $this->aggregator->getPermissionSource($user, 'posts.view');

            expect($source['type'])->toBe('direct');
        });

        it('identifies role permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
            $user->assignRole($role);

            $source = $this->aggregator->getPermissionSource($user, 'posts.edit');

            expect($source['type'])->toBe('role')
                ->and($source['source'])->toBe('editor');
        });
    });

    describe('getEffectiveRoles', function (): void {
        it('returns empty for objects without roles method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $roles = $this->aggregator->getEffectiveRoles($nonUser);

            expect($roles)->toBeEmpty();
        });
    });

    describe('clearRoleCache', function (): void {
        it('clears role cache', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            // Populate cache
            $this->aggregator->getEffectiveRolePermissions($role);

            $cacheKey = 'permissions:aggregated:role:' . $role->id;
            expect(Cache::has($cacheKey))->toBeTrue();

            $this->aggregator->clearRoleCache($role);

            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('clearAllCache', function (): void {
        it('clears all related caches', function (): void {
            // This just verifies no exception is thrown
            $this->aggregator->clearAllCache();
            expect(true)->toBeTrue();
        });
    });
});
