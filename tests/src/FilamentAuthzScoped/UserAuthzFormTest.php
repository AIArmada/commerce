<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

it('filters user role options to global roles and preserves scoped assignments', function (): void {
    config()->set('filament-authz.scoped_to_tenant', false);
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'global_only');
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

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
        $teamsKey => $scope->getKey(),
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
    config()->set('filament-authz.scoped_to_tenant', false);
    config()->set('filament-authz.user_resource.form.role_scope_mode', 'scoped_only');
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

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
        $teamsKey => $scope->getKey(),
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

it('scopes user role options to the active tenant when tenant scoping is enabled', function (): void {
    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '66666666-6666-4666-8666-666666666666',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '77777777-7777-4777-8777-777777777777',
        'label' => 'Scope B',
    ]);

    setPermissionsTeamId($scopeA->getKey());
    $scopeARole = Role::create([
        'name' => 'scope_a_manager',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_manager',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scopeA->getKey());

    $options = invokeProtectedStatic(UserAuthzForm::class, 'getRoleOptions');

    expect($options)->toHaveKey((string) $scopeARole->getKey())
        ->and($options)->not->toHaveKey((string) $scopeBRole->getKey());
});

it('rejects syncing roles from another tenant scope', function (): void {
    $user = User::query()->create([
        'name' => 'Scope Guard User',
        'email' => 'scope-guard@example.com',
        'password' => 'secret',
    ]);

    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '88888888-8888-4888-8888-888888888888',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => '99999999-9999-4999-8999-999999999999',
        'label' => 'Scope B',
    ]);

    setPermissionsTeamId($scopeA->getKey());
    Role::create([
        'name' => 'scope_a_editor',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_editor',
        'guard_name' => 'web',
    ]);

    setPermissionsTeamId($scopeA->getKey());

    expect(fn () => invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, [(string) $scopeBRole->getKey()]]))
        ->toThrow(AuthorizationException::class);
});

it('does not remove roles from another tenant scope while syncing current scope', function (): void {
    $user = User::query()->create([
        'name' => 'Scoped Isolation User',
        'email' => 'scoped-isolation@example.com',
        'password' => 'secret',
    ]);

    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'label' => 'Scope B',
    ]);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_only',
        'guard_name' => 'web',
    ]);
    $user->syncRoles([(string) $scopeBRole->getKey()]);

    setPermissionsTeamId($scopeA->getKey());

    invokeProtectedStatic(UserAuthzForm::class, 'syncRolesAcrossScopes', [$user, []]);

    expect(roleNamesFor($user, (string) $scopeA->getKey()))->toBe([])
        ->and(roleNamesFor($user, (string) $scopeB->getKey()))->toBe(['scope_b_only']);
});

it('scopes user resource query and route binding to the active tenant', function (): void {
    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        'label' => 'Scope B',
    ]);

    $scopeAUser = User::query()->create([
        'name' => 'Scope A User',
        'email' => 'scope-a-user@example.com',
        'password' => 'secret',
    ]);

    $scopeBUser = User::query()->create([
        'name' => 'Scope B User',
        'email' => 'scope-b-user@example.com',
        'password' => 'secret',
    ]);

    setPermissionsTeamId($scopeA->getKey());
    $scopeARole = Role::create([
        'name' => 'scope_a_user_role',
        'guard_name' => 'web',
    ]);
    $scopeAUser->syncRoles([(string) $scopeARole->getKey()]);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_user_role',
        'guard_name' => 'web',
    ]);
    $scopeBUser->syncRoles([(string) $scopeBRole->getKey()]);

    setPermissionsTeamId($scopeA->getKey());

    $visibleEmails = UserResource::getEloquentQuery()
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($visibleEmails)->toContain('scope-a-user@example.com')
        ->and($visibleEmails)->not->toContain('scope-b-user@example.com')
        ->and(UserResource::resolveRecordRouteBinding((string) $scopeAUser->getKey()))->not->toBeNull()
        ->and(UserResource::resolveRecordRouteBinding((string) $scopeBUser->getKey()))->toBeNull();
});

it('scopes permission assignment summary counts to the active tenant', function (): void {
    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'efefefef-efef-4fef-8fef-efefefefefef',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'f0f0f0f0-f0f0-4f0f-8f0f-f0f0f0f0f0f0',
        'label' => 'Scope B',
    ]);

    $permission = Permission::findOrCreate('orders.manage', 'web');

    setPermissionsTeamId($scopeA->getKey());
    $scopeARole = Role::create([
        'name' => 'scope_a_permission_manager',
        'guard_name' => 'web',
    ]);
    $scopeARole->syncPermissions([$permission]);

    $scopeAUser = User::query()->create([
        'name' => 'Scope A Permission User',
        'email' => 'scope-a-permission-user@example.com',
        'password' => 'secret',
    ]);
    $scopeAUser->givePermissionTo($permission);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_permission_manager',
        'guard_name' => 'web',
    ]);
    $scopeBRole->syncPermissions([$permission]);

    $scopeBUser = User::query()->create([
        'name' => 'Scope B Permission User',
        'email' => 'scope-b-permission-user@example.com',
        'password' => 'secret',
    ]);
    $scopeBUser->givePermissionTo($permission);

    setPermissionsTeamId($scopeA->getKey());

    $summary = invokeProtectedStatic(PermissionResource::class, 'getAssignmentSummaryText', [$permission->fresh()]);

    expect($summary)->toBe('Assigned to 1 role(s), and 1 user(s) directly.');
});

it('does not leak cross-tenant direct users in permission overview', function (): void {
    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'abab1111-abab-4111-8bab-abab1111abab',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'member-role-scope',
        'scopeable_id' => 'cdcd2222-cdcd-4222-8dcd-cdcd2222cdcd',
        'label' => 'Scope B',
    ]);

    $permission = Permission::findOrCreate('users.audit', 'web');

    setPermissionsTeamId($scopeA->getKey());
    $scopeAUser = User::query()->create([
        'name' => 'Scoped A Viewer',
        'email' => 'scoped-a-viewer@example.com',
        'password' => 'secret',
    ]);
    $scopeAUser->givePermissionTo($permission);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBUser = User::query()->create([
        'name' => 'Scoped B Viewer',
        'email' => 'scoped-b-viewer@example.com',
        'password' => 'secret',
    ]);
    $scopeBUser->givePermissionTo($permission);

    setPermissionsTeamId($scopeA->getKey());

    $renderedUsers = invokeProtectedStatic(PermissionResource::class, 'renderDirectUsers', [$permission->fresh()]);

    expect((string) $renderedUsers)->toContain('scoped-a-viewer@example.com')
        ->and((string) $renderedUsers)->not->toContain('scoped-b-viewer@example.com');
});

/**
 * @param  list<mixed>  $arguments
 */
function invokeProtectedStatic(string $className, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($className, $methodName);

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
