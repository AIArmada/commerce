<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
    config(['filament-authz.super_admin_role' => 'super_admin']);
});

describe('PermissionsDiffWidget', function (): void {
    it('has correct heading', function (): void {
        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('heading');

        expect($property->getValue($widget))->toBe('Permissions Overview');
    });

    it('denies access when user is not authenticated', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(PermissionsDiffWidget::canView())->toBeFalse();
    });

    it('allows access when user has permission.viewAny permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('permission.viewAny')
            ->andReturn(true);

        Auth::shouldReceive('user')->andReturn($user);

        expect(PermissionsDiffWidget::canView())->toBeTrue();
    });

    it('allows access when user has super admin role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('permission.viewAny')
            ->andReturn(false);
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(true);

        Auth::shouldReceive('user')->andReturn($user);

        expect(PermissionsDiffWidget::canView())->toBeTrue();
    });

    it('denies access when user has neither permission nor role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('permission.viewAny')
            ->andReturn(false);
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(PermissionsDiffWidget::canView())->toBeFalse();
    });

    it('returns stats array with correct structure', function (): void {
        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats)->toBeArray()
            ->and(count($stats))->toBe(3);
    });

    it('counts total permissions correctly', function (): void {
        Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe(3);
    });

    it('counts total roles correctly', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[1]->getValue())->toBe(2);
    });

    it('counts unused permissions correctly', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $assignedPerm = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);

        $role->givePermissionTo($assignedPerm);

        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        // 2 unused permissions (posts.delete, posts.view)
        expect($stats[2]->getValue())->toBe(2);
    });

    it('returns zero for unused permissions when all are assigned', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $perm1 = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

        $role->givePermissionTo([$perm1, $perm2]);

        $widget = new PermissionsDiffWidget;
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[2]->getValue())->toBe(0);
    });
});
