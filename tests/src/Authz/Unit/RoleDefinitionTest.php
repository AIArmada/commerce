<?php

declare(strict_types=1);

use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Authz\Features\Authorization\Models\Role;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

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
