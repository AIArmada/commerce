<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

beforeEach(function () {
    // Use single guard for predictable test output
    config()->set('filament-authz.guards', ['web']);
    config()->set('filament-authz.sync', [
        'permissions' => [
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'products.view',
        ],
        'roles' => [
            'admin' => ['orders.view', 'orders.create', 'orders.update', 'orders.delete'],
            'viewer' => ['orders.view', 'products.view'],
        ],
    ]);
});

describe('authz:sync command', function () {
    it('creates permissions from config', function () {
        $this->artisan('authz:sync')
            ->expectsOutput('Synced 5 permissions and 2 roles.')
            ->assertExitCode(0);

        expect(Permission::where('name', 'orders.view')->exists())->toBeTrue();
        expect(Permission::where('name', 'orders.create')->exists())->toBeTrue();
        expect(Permission::where('name', 'orders.update')->exists())->toBeTrue();
        expect(Permission::where('name', 'orders.delete')->exists())->toBeTrue();
        expect(Permission::where('name', 'products.view')->exists())->toBeTrue();
    });

    it('creates roles with permissions from config', function () {
        $this->artisan('authz:sync')->assertExitCode(0);

        $admin = Role::findByName('admin', 'web');
        expect($admin)->not->toBeNull();
        expect($admin->hasPermissionTo('orders.view'))->toBeTrue();
        expect($admin->hasPermissionTo('orders.create'))->toBeTrue();
        expect($admin->hasPermissionTo('orders.delete'))->toBeTrue();
        expect($admin->hasPermissionTo('products.view'))->toBeFalse();

        $viewer = Role::findByName('viewer', 'web');
        expect($viewer)->not->toBeNull();
        expect($viewer->hasPermissionTo('orders.view'))->toBeTrue();
        expect($viewer->hasPermissionTo('products.view'))->toBeTrue();
        expect($viewer->hasPermissionTo('orders.delete'))->toBeFalse();
    });

    it('is idempotent', function () {
        $this->artisan('authz:sync')->assertExitCode(0);
        $this->artisan('authz:sync')->assertExitCode(0);

        expect(Permission::where('name', 'orders.view')->count())->toBe(1);
        expect(Role::where('name', 'admin')->count())->toBe(1);
    });

    it('can flush cache after sync', function () {
        $this->artisan('authz:sync', ['--flush-cache' => true])
            ->expectsOutput('Permission cache flushed.')
            ->assertExitCode(0);
    });
});
