<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->userClass = config('auth.providers.users.model', Illuminate\Foundation\Auth\User::class);
});

test('can create role with guard', function () {
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->name)->toBe('Admin')
        ->and($role->guard_name)->toBe('web');
});

test('can create permission with guard', function () {
    $permission = Permission::create(['name' => 'edit posts', 'guard_name' => 'web']);

    expect($permission)->toBeInstanceOf(Permission::class)
        ->and($permission->name)->toBe('edit posts')
        ->and($permission->guard_name)->toBe('web');
});

test('can assign permission to role', function () {
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'edit articles', 'guard_name' => 'web']);

    $role->givePermissionTo($permission);

    expect($role->hasPermissionTo('edit articles'))->toBeTrue();
});

test('can assign role to user', function () {
    $user = $this->userClass::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $role = Role::create(['name' => 'Manager', 'guard_name' => 'web']);
    $user->assignRole($role);

    expect($user->hasRole('Manager'))->toBeTrue();
});

test('user can check permission via role', function () {
    $user = $this->userClass::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password'),
    ]);

    $role = Role::create(['name' => 'Author', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'publish posts', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect($user->can('publish posts'))->toBeTrue();
});

test('super admin bypasses all gates when configured', function () {
    config(['filament-permissions.super_admin_role' => 'Super Admin']);

    $user = $this->userClass::create([
        'name' => 'Super User',
        'email' => 'super@example.com',
        'password' => bcrypt('password'),
    ]);

    $superRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    $user->assignRole($superRole);

    Gate::define('some-random-ability', fn () => false);

    $this->actingAs($user);

    expect(Gate::allows('some-random-ability'))->toBeTrue();
});

test('can assign direct permission to user', function () {
    $user = $this->userClass::create([
        'name' => 'Direct User',
        'email' => 'direct@example.com',
        'password' => bcrypt('password'),
    ]);

    $permission = Permission::create(['name' => 'view reports', 'guard_name' => 'web']);
    $user->givePermissionTo($permission);

    expect($user->can('view reports'))->toBeTrue();
});

test('multi-guard roles work independently', function () {
    $webRole = Role::create(['name' => 'WebAdmin', 'guard_name' => 'web']);
    $adminRole = Role::create(['name' => 'AdminManager', 'guard_name' => 'admin']);

    expect($webRole->guard_name)->toBe('web')
        ->and($adminRole->guard_name)->toBe('admin');
});
