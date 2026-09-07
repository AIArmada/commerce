<?php

declare(strict_types=1);

use AIArmada\Authz\AuthzServiceProvider;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Support\ImpersonationScopeGuard;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

it('composes global super-admin and wildcard authorization hooks', function (): void {
    setPermissionsTeamId(null);

    $superAdmin = User::query()->create([
        'name' => 'Core Super Admin',
        'email' => 'core-super-admin@example.com',
        'password' => 'secret',
    ]);
    $superAdminRole = Role::create([
        'name' => (string) config('authz.super_admin_role'),
        'guard_name' => 'web',
    ]);
    $superAdmin->assignRole($superAdminRole);

    $owner = User::query()->create([
        'name' => 'Core Authorization Owner',
        'email' => 'core-authorization-owner@example.com',
        'password' => 'secret',
    ]);
    $wildcardUser = User::query()->create([
        'name' => 'Core Wildcard User',
        'email' => 'core-wildcard-user@example.com',
        'password' => 'secret',
    ]);
    $regularUser = User::query()->create([
        'name' => 'Core Regular User',
        'email' => 'core-regular-user@example.com',
        'password' => 'secret',
    ]);

    setPermissionsTeamId($owner->getKey());
    $wildcardPermission = Permission::findOrCreate('orders.*', 'web');
    $wildcardUser->givePermissionTo($wildcardPermission);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Gate::forUser($superAdmin)->allows('unlisted.ability'))->toBeTrue()
        ->and(Gate::forUser($wildcardUser)->allows('orders.view'))->toBeTrue()
        ->and(Gate::forUser($wildcardUser)->allows('users.view'))->toBeFalse()
        ->and(Gate::forUser($regularUser)->allows('unlisted.ability'))->toBeFalse();
});

it('denies impersonation targets outside the active team', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Core Scope A',
        'email' => 'core-scope-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Core Scope B',
        'email' => 'core-scope-b@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Core Cross Scope Target',
        'email' => 'core-cross-scope-target@example.com',
        'password' => 'secret',
    ]);

    setPermissionsTeamId($ownerA->getKey());
    $role = Role::create([
        'name' => 'core_scope_a_support',
        'guard_name' => 'web',
    ]);
    $target->syncRoles([$role->getKey()]);

    setPermissionsTeamId($ownerB->getKey());

    expect(ImpersonationScopeGuard::canAccessTarget($target))->toBeFalse()
        ->and(ImpersonationScopeGuard::applyScopeToUserQuery(User::query()->whereKey($target->getKey()))->exists())
        ->toBeFalse();
});

it('leaves impersonation queries unscoped when enforcement is explicitly disabled', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Core Disabled Scope A',
        'email' => 'core-disabled-scope-a@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Core Disabled Scope B',
        'email' => 'core-disabled-scope-b@example.com',
        'password' => 'secret',
    ]);
    $target = User::query()->create([
        'name' => 'Core Disabled Scope Target',
        'email' => 'core-disabled-scope-target@example.com',
        'password' => 'secret',
    ]);

    setPermissionsTeamId($ownerA->getKey());
    $role = Role::create([
        'name' => 'core_disabled_scope_support',
        'guard_name' => 'web',
    ]);
    $target->syncRoles([$role->getKey()]);

    setPermissionsTeamId($ownerB->getKey());
    config()->set('authz.scopes.enforce', false);

    expect(ImpersonationScopeGuard::canAccessTarget($target))->toBeTrue();
});

it('rejects invalid permission separators during provider boot', function (): void {
    config()->set('authz.permissions.separator', 'ab');

    try {
        expect(function (): void {
            (new AuthzServiceProvider(app()))->boot();
        })
            ->toThrow(InvalidArgumentException::class, 'exactly one non-alphanumeric character');
    } finally {
        config()->set('authz.permissions.separator', '.');
    }
});

it('rejects enabled Authz scopes when Spatie teams are disabled', function (): void {
    config()->set('authz.scopes.enabled', true);
    config()->set('permission.teams', false);

    try {
        expect(function (): void {
            (new AuthzServiceProvider(app()))->boot();
        })
            ->toThrow(InvalidArgumentException::class, 'require Spatie permission teams');
    } finally {
        config()->set('authz.scopes.enabled', false);
        config()->set('permission.teams', true);
    }
});
