<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

it('filters user role options to global roles and preserves scoped assignments', function (): void {
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'global_only');

    $user = User::query()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '11111111-1111-4111-8111-111111111111',
        'label' => 'Shared Scope Alpha',
    ]);

    $globalRole = Role::create([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scope->getKey());
    $scopedRole = Role::create([
        'name' => 'member_admin',
        'guard_name' => 'web',
    ]);
    $user->syncRoles([$scopedRole->getKey()]);

    $options = invokeProtectedStatic(UserAuthzForm::class, 'getRoleOptions');

    expect($options)->toHaveKey((string) $globalRole->getKey())
        ->and($options)->not->toHaveKey((string) $scopedRole->getKey());

    invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, []]);

    expect(roleNamesFor($user, null))->toBe([])
        ->and(roleNamesFor($user, (string) $scope->getKey()))->toBe(['member_admin']);
});

it('preserves scoped assignments in global-only mode when teams are enabled without tenant scoping', function (): void {
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'global_only');
    config()->set('filament-authz.scoped_to_tenant', false);
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

    $user = User::query()->create([
        'name' => 'Alice Two',
        'email' => 'alice-two@example.com',
        'password' => 'secret',
    ]);

    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '11111111-1111-4111-8111-222222222222',
        'label' => 'Shared Scope Beta',
    ]);

    setPermissionsTeamId($scope->getKey());
    $scopedRole = Role::create([
        'name' => 'member_owner',
        'guard_name' => 'web',
        $teamsKey => $scope->getKey(),
    ]);
    $user->syncRoles([$scopedRole->getKey()]);

    setPermissionsTeamId(null);

    $options = invokeProtectedStatic(UserAuthzForm::class, 'getRoleOptions');

    expect($options)->not->toHaveKey((string) $scopedRole->getKey());

    invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, []]);

    expect(roleNamesFor($user, null))->toBe([])
        ->and(roleNamesFor($user, (string) $scope->getKey()))->toBe(['member_owner']);
});

it('filters user role options to scoped roles and preserves global assignments', function (): void {
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'scoped_only');

    $user = User::query()->create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret',
    ]);

    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '22222222-2222-4222-8222-222222222222',
        'label' => 'Shared Scope Gamma',
    ]);

    $globalRole = Role::create([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scope->getKey());
    $scopedRole = Role::create([
        'name' => 'member_editor',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->syncRoles([$globalRole->getKey()]);

    $options = invokeProtectedStatic(UserAuthzForm::class, 'getRoleOptions');

    expect($options)->toHaveKey((string) $scopedRole->getKey())
        ->and($options)->not->toHaveKey((string) $globalRole->getKey());

    invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, []]);

    expect(roleNamesFor($user, null))->toBe(['super_admin'])
        ->and(roleNamesFor($user, (string) $scope->getKey()))->toBe([]);
});

it('preserves global assignments in scoped-only mode when teams are enabled without tenant scoping', function (): void {
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'scoped_only');
    config()->set('filament-authz.scoped_to_tenant', false);
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

    $user = User::query()->create([
        'name' => 'Bob Two',
        'email' => 'bob-two@example.com',
        'password' => 'secret',
    ]);

    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '22222222-2222-4222-8222-333333333333',
        'label' => 'Shared Scope Delta',
    ]);

    $globalRole = Role::create([
        'name' => 'super_admin_two',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);
    $user->syncRoles([$globalRole->getKey()]);

    setPermissionsTeamId($scope->getKey());
    $scopedRole = Role::create([
        'name' => 'member_editor_two',
        'guard_name' => 'web',
        $teamsKey => $scope->getKey(),
    ]);

    setPermissionsTeamId(null);

    $options = invokeProtectedStatic(UserAuthzForm::class, 'getRoleOptions');

    expect($options)->toHaveKey((string) $scopedRole->getKey())
        ->and($options)->not->toHaveKey((string) $globalRole->getKey());

    invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, []]);

    expect(roleNamesFor($user, null))->toBe(['super_admin_two'])
        ->and(roleNamesFor($user, (string) $scope->getKey()))->toBe([]);
});

it('uses configured role resource scope options', function (): void {
    $scope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '33333333-3333-4333-8333-333333333333',
        'label' => 'Shared Scope Epsilon',
    ]);

    config()->set('filament-authz.role_resource.scope_options', [
        (string) $scope->getKey() => 'Only Shared Scope',
    ]);

    $options = invokeProtectedStatic(RoleResource::class, 'getScopeOptions');

    expect($options)->toBe([
        (string) $scope->getKey() => 'Only Shared Scope',
    ]);
});

it('limits the central role resource query to global roles and configured scopes', function (): void {
    config()->set('filament-authz.central_app', true);

    $allowedScope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '44444444-4444-4444-8444-444444444444',
        'label' => 'Allowed Scope',
    ]);

    $excludedScope = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '55555555-5555-4555-8555-555555555555',
        'label' => 'Excluded Scope',
    ]);

    setPermissionsTeamId(null);
    $globalRole = Role::create([
        'name' => 'global_admin',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($allowedScope->getKey());
    $allowedRole = Role::create([
        'name' => 'allowed_role',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($excludedScope->getKey());
    $excludedRole = Role::create([
        'name' => 'excluded_role',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId(null);

    config()->set('filament-authz.role_resource.scope_options', [
        (string) $allowedScope->getKey() => 'Allowed Scope',
    ]);

    $visibleRoleNames = RoleResource::getEloquentQuery()
        ->orderBy('name')
        ->pluck('name')
        ->all();

    expect($visibleRoleNames)->toBe(['allowed_role', 'global_admin'])
        ->and(RoleResource::resolveRecordRouteBinding((string) $globalRole->getKey()))->not->toBeNull()
        ->and(RoleResource::resolveRecordRouteBinding((string) $allowedRole->getKey()))->not->toBeNull()
        ->and(RoleResource::resolveRecordRouteBinding((string) $excludedRole->getKey()))->toBeNull();
});

/**
 * @param  list<mixed>  $arguments
 */
function invokeProtectedStatic(string $className, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($className, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs(null, $arguments);
}

/**
 * @return list<string>
 */
function roleNamesFor(User $user, ?string $scopeId): array
{
    $previousScope = getPermissionsTeamId();
    setPermissionsTeamId($scopeId);

    try {
        return $user->fresh()->getRoleNames()->values()->all();
    } finally {
        setPermissionsTeamId($previousScope);
    }
}
