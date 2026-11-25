<?php

declare(strict_types=1);

use AIArmada\Authz\Features\Authorization\Actions\EnsurePermissions;
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Data\RecordReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

it('creates roles with existing permissions through the owning API', function (): void {
    $permission = Permission::create(['name' => 'role-definition.view', 'guard_name' => 'web']);

    $saved = app(RoleAdministrationApi::class)->saveRoleDefinition(null, 'definition-role', 'web', null, [$permission->name]);

    expect(Role::query()->findOrFail($saved->id)->permissions()->pluck('name')->all())->toBe([$permission->name]);
});

it('rolls back a role creation if the selected permissions do not exist', function (): void {
    expect(fn () => app(RoleAdministrationApi::class)->saveRoleDefinition(null, 'invalid-definition-role', 'web', null, ['missing.permission']))
        ->toThrow(PermissionDoesNotExist::class);

    expect(Role::query()->where('name', 'invalid-definition-role')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'missing.permission')->exists())->toBeFalse();
});

it('rolls back the role definition and existing permissions on an invalid edit', function (): void {
    $role = Role::create(['name' => 'atomic-definition-role', 'guard_name' => 'web']);
    $permission = Permission::findOrCreate('atomic-definition.view', 'web');
    $role->givePermissionTo($permission);

    expect(fn () => app(RoleAdministrationApi::class)->saveRoleDefinition((string) $role->getKey(), 'renamed-role', 'web', null, ['missing.permission']))
        ->toThrow(PermissionDoesNotExist::class);

    expect($role->fresh()->name)->toBe('atomic-definition-role')
        ->and($role->permissions()->pluck('name')->all())->toBe(['atomic-definition.view']);
});

it('removes role permission assignments when deleting a permission through the owning API', function (): void {
    $api = app(RoleAdministrationApi::class);
    $id = $api->savePermissionDefinition(null, 'definition.delete', 'web');
    $role = $api->saveRoleDefinition(null, 'definition-delete-role', 'web', null, ['definition.delete']);

    expect($api->deletePermissionDefinition($id))->toBeTrue()
        ->and(Permission::query()->find($id))->toBeNull()
        ->and(Role::query()->findOrFail($role->id)->permissions()->count())->toBe(0);
});

it('trims whitespace from role and permission names', function (): void {
    $api = app(RoleAdministrationApi::class);

    $permissionId = $api->savePermissionDefinition(null, '  trimmed.permission  ', 'web');
    $role = $api->saveRoleDefinition(null, '  trimmed-role  ', 'web', null, ['trimmed.permission']);

    expect(Permission::query()->findOrFail($permissionId)->name)->toBe('trimmed.permission')
        ->and(Role::query()->findOrFail($role->id)->name)->toBe('trimmed-role');
});

it('rejects a non-uuid role scope when authz scopes are enabled', function (): void {
    $registrar = app(PermissionRegistrar::class);
    $previousTeams = $registrar->teams;
    $registrar->teams = true;
    config()->set('authz.scopes.enabled', true);

    try {
        expect(fn () => app(RoleAdministrationApi::class)->saveRoleDefinition(null, 'scoped-role', 'web', 'not-a-uuid', []))
            ->toThrow(AuthorizationException::class);
    } finally {
        $registrar->teams = $previousTeams;
        config()->set('authz.scopes.enabled', false);
    }
});

it('returns false when assigning a missing role or a missing user record', function (): void {
    $api = app(RoleAdministrationApi::class);
    $user = User::factory()->create();
    $record = new RecordReference($user->getMorphClass(), (string) $user->getKey());

    expect($api->assignExistingRole($record, 'missing-role', 'web', null))->toBeFalse();

    Role::findOrCreate('assignable-role', 'web');
    $ghost = new RecordReference(User::class, (string) Str::uuid());

    expect($api->assignExistingRole($ghost, 'assignable-role', 'web', null))->toBeFalse();
});

it('throws when syncing roles or permissions for a missing user', function (): void {
    $api = app(RoleAdministrationApi::class);
    $ghost = new RecordReference(User::class, (string) Str::uuid());

    expect(fn () => $api->syncUserRoles($ghost, [], 'web', false, 'all', null, true))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $api->syncUserPermissions($ghost, [], 'web'))
        ->toThrow(AuthorizationException::class);
});

it('detaches multiple roles in a single sync', function (): void {
    $user = User::factory()->create();
    $user->assignRole(
        Role::findOrCreate('sync-role-one', 'web'),
        Role::findOrCreate('sync-role-two', 'web'),
    );

    expect($user->roles()->count())->toBe(2);

    $api = app(RoleAdministrationApi::class);
    $api->syncUserRoles(new RecordReference($user->getMorphClass(), (string) $user->getKey()), [], 'web', false, 'all', null, true);

    expect($user->fresh()->roles()->count())->toBe(0);
});

it('bulk creates permissions without duplicates', function (): void {
    app(EnsurePermissions::class)->execute('web', ['bulk.permission.one', 'bulk.permission.two', 'bulk.permission.one']);

    expect(Permission::query()->whereIn('name', ['bulk.permission.one', 'bulk.permission.two'])->count())->toBe(2);
});

it('assigns and revokes roles through the administration api', function (): void {
    $user = User::factory()->create();
    $role = Role::findOrCreate('api-assignable-role', 'web');
    $api = app(RoleAdministrationApi::class);
    $record = new RecordReference($user->getMorphClass(), (string) $user->getKey());

    $api->assignRole($record, (string) $role->getKey());

    expect($user->fresh()->hasRole('api-assignable-role'))->toBeTrue();

    $api->revokeRole($record, 'api-assignable-role');

    expect($user->fresh()->hasRole('api-assignable-role'))->toBeFalse();
});
