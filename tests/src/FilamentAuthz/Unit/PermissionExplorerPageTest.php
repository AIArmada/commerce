<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
    config(['filament-authz.super_admin_role' => 'super_admin']);
    config(['filament-authz.navigation.group' => 'Authorization']);
});

describe('PermissionExplorer', function (): void {
    it('has correct title', function (): void {
        $reflection = new ReflectionClass(PermissionExplorer::class);
        $property = $reflection->getProperty('title');

        expect($property->getValue())->toBe('Permission Explorer');
    });

    it('has correct view', function (): void {
        $page = new PermissionExplorer;
        $reflection = new ReflectionClass($page);
        $property = $reflection->getProperty('view');

        expect($property->getValue($page))->toBe('filament-authz::pages.permission-explorer');
    });

    it('gets navigation group from config', function (): void {
        expect(PermissionExplorer::getNavigationGroup())->toBe('Authorization');
    });

    it('denies access when user is not authenticated', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(PermissionExplorer::canAccess())->toBeFalse();
    });

    it('allows access when user has permission.viewAny permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('permission.viewAny')
            ->andReturn(true);
        // hasRole is still called due to code structure (both checks run)
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(PermissionExplorer::canAccess())->toBeTrue();
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

        expect(PermissionExplorer::canAccess())->toBeTrue();
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

        expect(PermissionExplorer::canAccess())->toBeFalse();
    });

    it('groups permissions by first part of name', function (): void {
        Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);

        $page = new PermissionExplorer;
        $grouped = $page->getPermissionsGrouped();

        expect($grouped)->toBeArray()
            ->and(array_keys($grouped))->toContain('posts', 'users')
            ->and(count($grouped['posts']))->toBe(2)
            ->and(count($grouped['users']))->toBe(1);
    });

    it('returns empty array when no permissions exist', function (): void {
        $page = new PermissionExplorer;
        $grouped = $page->getPermissionsGrouped();

        expect($grouped)->toBeArray()->and($grouped)->toBe([]);
    });

    it('returns roles with permission counts', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $perm1 = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'posts.create', 'guard_name' => 'web']);

        $role1->givePermissionTo([$perm1, $perm2]);
        $role2->givePermissionTo($perm1);

        $page = new PermissionExplorer;
        $roles = $page->getRolesWithPermissionCounts();

        expect($roles)->toBeArray()
            ->and(count($roles))->toBe(2);

        $adminRole = collect($roles)->firstWhere('name', 'admin');
        $editorRole = collect($roles)->firstWhere('name', 'editor');

        expect($adminRole['permissions_count'])->toBe(2)
            ->and($editorRole['permissions_count'])->toBe(1);
    });

    it('includes role names for each permission', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $page = new PermissionExplorer;
        $grouped = $page->getPermissionsGrouped();

        $postsPermissions = $grouped['posts'];
        $viewPermission = collect($postsPermissions)->firstWhere('name', 'posts.view');

        expect($viewPermission['roles'])->toContain('admin');
    });
});
