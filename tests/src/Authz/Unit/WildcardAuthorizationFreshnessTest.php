<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

/**
 * Regression coverage for AZ-02: Gate must reflect wildcard and super-admin
 * assignment changes on the first check after a supported mutation, for both
 * already-loaded and fresh user instances.
 */
function az02User(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-02 User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function az02Wildcard(string $name = 'audit.*'): Permission
{
    return Permission::findOrCreate($name, 'web');
}

it('denies the next gate check after a wildcard grant is revoked', function (): void {
    $user = az02User('az02-revoke@example.com');

    Permission::findOrCreate('audit.delete', 'web');

    $user->givePermissionTo(az02Wildcard());

    expect($user->can('audit.delete'))->toBeTrue();

    $user->revokePermissionTo(az02Wildcard());

    expect($user->hasPermissionTo('audit.delete'))->toBeFalse()
        ->and($user->can('audit.delete'))->toBeFalse();
});

it('allows a wildcard on the first check after it is granted', function (): void {
    $user = az02User('az02-grant@example.com');

    Permission::findOrCreate('audit.delete', 'web');

    expect($user->can('audit.delete'))->toBeFalse();

    $user->givePermissionTo(az02Wildcard());

    expect($user->can('audit.delete'))->toBeTrue();
});

it('sees a wildcard-bearing role removal immediately', function (): void {
    $user = az02User('az02-role-removal@example.com');

    Permission::findOrCreate('audit.delete', 'web');

    $role = Role::create(['name' => 'audit-manager', 'guard_name' => 'web']);
    $role->givePermissionTo(az02Wildcard());

    $user->assignRole($role);

    expect($user->can('audit.delete'))->toBeTrue();

    $user->removeRole($role);

    expect($user->can('audit.delete'))->toBeFalse();
});

it('sees role permission syncs immediately', function (): void {
    $user = az02User('az02-role-sync@example.com');

    Permission::findOrCreate('audit.delete', 'web');
    Permission::findOrCreate('audit.approve.one', 'web');

    $role = Role::create(['name' => 'audit-syncer', 'guard_name' => 'web']);
    $user->assignRole($role);

    expect($user->can('audit.delete'))->toBeFalse()
        ->and($user->can('audit.approve.one'))->toBeFalse();

    $role->givePermissionTo(az02Wildcard());

    expect($user->can('audit.delete'))->toBeTrue();

    $role->syncPermissions([az02Wildcard('audit.approve.*')]);

    expect($user->can('audit.delete'))->toBeFalse()
        ->and($user->can('audit.approve.one'))->toBeTrue();
});

it('drops the super-admin verdict when the role is removed', function (): void {
    $user = az02User('az02-super-admin@example.com');

    $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    expect($user->can('anything.at.all'))->toBeTrue();

    $user->removeRole($role);

    expect($user->can('anything.at.all'))->toBeFalse();
});

it('keeps two loaded instances coherent after grant and revoke', function (): void {
    $user = az02User('az02-two-instances@example.com');

    Permission::findOrCreate('audit.delete', 'web');

    $first = User::query()->findOrFail($user->getKey());
    $second = User::query()->findOrFail($user->getKey());

    $first->givePermissionTo(az02Wildcard());

    expect($first->can('audit.delete'))->toBeTrue()
        ->and($second->can('audit.delete'))->toBeTrue();

    $first->revokePermissionTo(az02Wildcard());

    expect($first->can('audit.delete'))->toBeFalse()
        ->and($second->can('audit.delete'))->toBeFalse();
});

it('does not leak wildcard verdicts across team scopes', function (): void {
    $user = az02User('az02-team-leak@example.com');

    Permission::findOrCreate('audit.delete', 'web');

    $ownerA = az02User('az02-team-owner-a@example.com');
    $ownerB = az02User('az02-team-owner-b@example.com');

    setPermissionsTeamId($ownerA->getKey());
    $user->givePermissionTo(az02Wildcard());

    setPermissionsTeamId($ownerA->getKey());

    expect($user->can('audit.delete'))->toBeTrue();

    setPermissionsTeamId($ownerB->getKey());

    expect($user->can('audit.delete'))->toBeFalse();

    setPermissionsTeamId($ownerA->getKey());

    expect($user->can('audit.delete'))->toBeTrue();

    setPermissionsTeamId(null);
});
