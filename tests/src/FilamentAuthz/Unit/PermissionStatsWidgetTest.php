<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
});

describe('PermissionStatsWidget', function (): void {
    it('has correct sort order', function (): void {
        $reflection = new ReflectionClass(PermissionStatsWidget::class);
        $property = $reflection->getProperty('sort');

        expect($property->getValue())->toBe(1);
    });

    it('returns stats array with all required stats', function (): void {
        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats)->toBeArray()
            ->and(count($stats))->toBe(4);
    });

    it('counts total roles correctly', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe(3);
    });

    it('counts total permissions correctly', function (): void {
        Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[1]->getValue())->toBe(2);
    });

    it('counts users with roles correctly', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('countUsersWithRoles');

        $count = $method->invoke($widget);

        expect($count)->toBe(1);
    });

    it('scopes user role counts to the current owner', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Default Owner User',
            'email' => 'default-owner-user@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $otherOwner = User::create([
            'name' => 'Other Owner',
            'email' => 'other-owner@example.com',
            'password' => bcrypt('password'),
        ]);

        OwnerContext::withOwner($otherOwner, function (): void {
            $role = Role::create(['name' => 'other-admin', 'guard_name' => 'web']);
            $user = User::create([
                'name' => 'Other Owner User',
                'email' => 'other-owner-user@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        });

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('countUsersWithRoles');

        $count = $method->invoke($widget);

        expect($count)->toBe(1);
    });

    it('counts unassigned permissions correctly', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $assignedPermission = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        $unassignedPermission = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

        $role->givePermissionTo($assignedPermission);

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('countUnassignedPermissions');

        $count = $method->invoke($widget);

        expect($count)->toBe(1);
    });

    it('returns zero for unassigned permissions when all are assigned', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('countUnassignedPermissions');

        $count = $method->invoke($widget);

        expect($count)->toBe(0);
    });

    it('returns zero counts when no data exists', function (): void {
        $widget = new PermissionStatsWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getStats');

        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe(0)
            ->and($stats[1]->getValue())->toBe(0)
            ->and($stats[2]->getValue())->toBe(0)
            ->and($stats[3]->getValue())->toBe(0);
    });
});
