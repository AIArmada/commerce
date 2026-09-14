<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Services\PermissionKeyBuilder;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\RoleAlreadyExists;

enum AuthzRepairProbe: string
{
    case RoleName = 'repair-probe-role';
    case PermissionName = 'repair.probe';
}

it('throws a domain exception on duplicate role creation', function (): void {
    setPermissionsTeamId(null);

    Role::create(['name' => 'repair-duplicate-role', 'guard_name' => 'web']);

    expect(fn (): mixed => Role::create(['name' => 'repair-duplicate-role', 'guard_name' => 'web']))
        ->toThrow(RoleAlreadyExists::class);
});

it('converts backed enums when creating roles and permissions', function (): void {
    setPermissionsTeamId(null);

    $role = Role::create(['name' => AuthzRepairProbe::RoleName, 'guard_name' => 'web']);
    $permission = Permission::findOrCreate(AuthzRepairProbe::PermissionName, 'web');

    expect($role->getAttribute('name'))->toBe('repair-probe-role')
        ->and($permission->getAttribute('name'))->toBe('repair.probe')
        ->and(fn (): mixed => Permission::create(['name' => AuthzRepairProbe::PermissionName, 'guard_name' => 'web']))
        ->toThrow(PermissionAlreadyExists::class);
});

it('rejects unknown permission key cases', function (): void {
    expect(fn (): string => app(PermissionKeyBuilder::class)->formatCase('Order', 'weird'))
        ->toThrow(InvalidArgumentException::class);
});

it('compiles nested expressions in the canBeImpersonated directive', function (): void {
    $compiled = app('blade.compiler')->compileString("@canBeImpersonated(\$user->target(['a' => 1]), 'web')x@endCanBeImpersonated");

    expect($compiled)->toContain("\\AIArmada\\Authz\\can_be_impersonated(\$user->target(['a' => 1]), 'web')");
});

it('registers the leave-impersonation route behind auth middleware', function (): void {
    $route = app('router')->getRoutes()->getByName('authz.leave-impersonation');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('auth', 'web');
});

it('preserves an explicitly supplied team id over the ambient team', function (): void {
    config()->set('authz.scopes.enforce', true);

    $ambientTeamId = (string) Str::uuid();
    $explicitTeamId = (string) Str::uuid();

    setPermissionsTeamId($ambientTeamId);

    $role = Role::create(['name' => 'explicit-team-role', 'guard_name' => 'web', 'team_id' => $explicitTeamId]);

    expect($role->getAttribute('team_id'))->toBe($explicitTeamId)
        ->and($role->fresh()->getAttribute('team_id'))->toBe($explicitTeamId);
});
