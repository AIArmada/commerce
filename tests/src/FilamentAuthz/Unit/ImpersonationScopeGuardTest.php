<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Support\ImpersonationScopeGuard;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(null);
});

it('allows impersonation when target has role assignment in current scope', function (): void {
    $target = User::query()->create([
        'name' => 'Scoped Target',
        'email' => 'scoped-target@example.com',
        'password' => 'secret',
    ]);

    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => '11111111-1111-4111-8111-111111111111',
        'label' => 'Scope A',
    ]);

    setPermissionsTeamId($scopeA->getKey());

    $role = Role::create([
        'name' => 'scope_a_support',
        'guard_name' => 'web',
    ]);

    $target->syncRoles([(string) $role->getKey()]);

    expect(ImpersonationScopeGuard::canAccessTarget($target))->toBeTrue();
});

it('blocks impersonation when target is outside current scope', function (): void {
    $target = User::query()->create([
        'name' => 'Cross Scope Target',
        'email' => 'cross-scope-target@example.com',
        'password' => 'secret',
    ]);

    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => '22222222-2222-4222-8222-222222222222',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => '33333333-3333-4333-8333-333333333333',
        'label' => 'Scope B',
    ]);

    setPermissionsTeamId($scopeA->getKey());

    $role = Role::create([
        'name' => 'scope_a_only',
        'guard_name' => 'web',
    ]);

    $target->syncRoles([(string) $role->getKey()]);

    setPermissionsTeamId($scopeB->getKey());

    expect(ImpersonationScopeGuard::canAccessTarget($target))->toBeFalse();
});

it('allows impersonation when target has direct permission assignment in current scope', function (): void {
    $target = User::query()->create([
        'name' => 'Direct Permission Target',
        'email' => 'direct-permission-target@example.com',
        'password' => 'secret',
    ]);

    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => '44444444-4444-4444-8444-444444444444',
        'label' => 'Scope A',
    ]);

    setPermissionsTeamId($scopeA->getKey());

    $permission = Permission::findOrCreate('users.support', 'web');
    $target->givePermissionTo($permission);

    expect(ImpersonationScopeGuard::canAccessTarget($target))->toBeTrue();
});

it('filters user queries to current scope assignments', function (): void {
    $scopeA = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => 'abababab-abab-4bab-8bab-abababababab',
        'label' => 'Scope A',
    ]);

    $scopeB = AuthzScope::query()->create([
        'scopeable_type' => 'impersonation-scope',
        'scopeable_id' => 'cdcdcdcd-cdcd-4dcd-8dcd-cdcdcdcdcdcd',
        'label' => 'Scope B',
    ]);

    $scopeAUser = User::query()->create([
        'name' => 'Scope A Member',
        'email' => 'scope-a-member@example.com',
        'password' => 'secret',
    ]);

    $scopeBUser = User::query()->create([
        'name' => 'Scope B Member',
        'email' => 'scope-b-member@example.com',
        'password' => 'secret',
    ]);

    setPermissionsTeamId($scopeA->getKey());
    $scopeARole = Role::create([
        'name' => 'scope_a_support_query',
        'guard_name' => 'web',
    ]);
    $scopeAUser->syncRoles([(string) $scopeARole->getKey()]);

    setPermissionsTeamId($scopeB->getKey());
    $scopeBRole = Role::create([
        'name' => 'scope_b_support_query',
        'guard_name' => 'web',
    ]);
    $scopeBUser->syncRoles([(string) $scopeBRole->getKey()]);

    setPermissionsTeamId($scopeA->getKey());

    $visibleUserEmails = ImpersonationScopeGuard::applyScopeToUserQuery(User::query())
        ->orderBy('email')
        ->pluck('email')
        ->all();

    expect($visibleUserEmails)->toContain('scope-a-member@example.com')
        ->and($visibleUserEmails)->not->toContain('scope-b-member@example.com');
});
