<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

test('role resource does not register navigation when unauthenticated', function (): void {
    expect(RoleResource::shouldRegisterNavigation())->toBeFalse();
});

test('role resource registers navigation when user can view roles', function (): void {
    $user = User::create([
        'name' => 'Nav User',
        'email' => 'nav-user@example.com',
        'password' => bcrypt('password'),
    ]);

    Permission::create(['name' => 'role.viewAny', 'guard_name' => 'web']);
    $user->givePermissionTo('role.viewAny');

    $this->actingAs($user);

    expect(RoleResource::shouldRegisterNavigation())->toBeTrue();
});

test('role resource registers navigation when user has super admin role', function (): void {
    config()->set('filament-authz.super_admin_role', 'Super Admin');

    $user = User::create([
        'name' => 'Super Nav User',
        'email' => 'super-nav-user@example.com',
        'password' => bcrypt('password'),
    ]);

    Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    $user->assignRole('Super Admin');

    $this->actingAs($user);

    expect(RoleResource::shouldRegisterNavigation())->toBeTrue();
});
