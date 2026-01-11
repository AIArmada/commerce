<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'gate-test-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
});

describe('super admin bypass', function () {
    it('grants all permissions to super admin role', function () {
        $superAdminRole = config('filament-authz.super_admin_role');
        $role = Role::findOrCreate($superAdminRole, 'web');
        $this->user->assignRole($role);

        $this->actingAs($this->user);

        expect(Gate::allows('any.random.permission'))->toBeTrue();
        expect(Gate::allows('orders.view'))->toBeTrue();
        expect(Gate::allows('nonexistent.ability'))->toBeTrue();
    });

    it('does not grant permissions to regular users', function () {
        $role = Role::findOrCreate('editor', 'web');
        $this->user->assignRole($role);

        $this->actingAs($this->user);

        expect(Gate::allows('orders.view'))->toBeFalse();
    });
});

describe('wildcard permissions', function () {
    it('grants access via prefix wildcard', function () {
        $permission = Permission::findOrCreate('orders.*', 'web');
        $this->user->givePermissionTo($permission);

        $this->actingAs($this->user);

        expect(Gate::allows('orders.view'))->toBeTrue();
        expect(Gate::allows('orders.create'))->toBeTrue();
        expect(Gate::allows('orders.delete'))->toBeTrue();
    });

    it('does not grant access to different prefix', function () {
        $permission = Permission::findOrCreate('orders.*', 'web');
        $this->user->givePermissionTo($permission);

        $this->actingAs($this->user);

        expect(Gate::allows('users.view'))->toBeFalse();
        expect(Gate::allows('products.create'))->toBeFalse();
    });

    it('grants access via universal wildcard', function () {
        $permission = Permission::findOrCreate('*', 'web');
        $this->user->givePermissionTo($permission);

        $this->actingAs($this->user);

        expect(Gate::allows('anything.here'))->toBeTrue();
        expect(Gate::allows('orders.view'))->toBeTrue();
    });

    it('grants access via suffix wildcard', function () {
        $permission = Permission::findOrCreate('*.view', 'web');
        $this->user->givePermissionTo($permission);

        $this->actingAs($this->user);

        expect(Gate::allows('orders.view'))->toBeTrue();
        expect(Gate::allows('products.view'))->toBeTrue();
        expect(Gate::allows('orders.create'))->toBeFalse();
    });
});
