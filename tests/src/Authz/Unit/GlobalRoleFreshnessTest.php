<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression coverage for AZ-02: the super-admin Gate verdict must read the
 * current persisted assignment on every check, including when the permission
 * registrar has teams disabled and an already-loaded user instance carries a
 * stale roles relation.
 */
function az02bUseTeams(bool $teams): void
{
    config()->set('permission.teams', $teams);
    config()->set('permission.team_resolver', DefaultTeamResolver::class);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class);

    setPermissionsTeamId(null);
}

function az02bUser(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-02B User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function az02bSuperAdminRole(): Role
{
    return Role::create([
        'name' => 'Super Admin',
        'guard_name' => 'web',
    ]);
}

it('reflects a super-admin revocation on the next gate check when teams are disabled', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-revoke-first@example.com');
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    $second = User::query()->findOrFail($first->getKey());

    // The first check loads the then-valid roles relation on the instance.
    expect($second->can('anything.at.all'))->toBeTrue();

    $first->removeRole($role);

    expect($second->can('anything.at.all'))->toBeFalse()
        ->and($first->can('anything.at.all'))->toBeFalse();
});

it('reflects a super-admin revocation through the stale instance when teams are disabled', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-revoke-second@example.com');
    $second = User::query()->findOrFail($first->getKey());
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    expect($first->can('anything.at.all'))->toBeTrue()
        ->and($second->can('anything.at.all'))->toBeTrue();

    $second->removeRole($role);

    expect($first->can('anything.at.all'))->toBeFalse()
        ->and($second->can('anything.at.all'))->toBeFalse();
});

it('reflects a super-admin grant on the next gate check when teams are disabled', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-grant-first@example.com');
    $second = User::query()->findOrFail($first->getKey());
    $role = az02bSuperAdminRole();

    // Warm the (empty) roles relation on the pre-grant instance.
    expect($second->can('anything.at.all'))->toBeFalse();

    $first->assignRole($role);

    expect($second->can('anything.at.all'))->toBeTrue()
        ->and($first->can('anything.at.all'))->toBeTrue();
});

it('reflects a super-admin sync through either loaded instance when teams are disabled', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-sync-first@example.com');
    $second = User::query()->findOrFail($first->getKey());
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    expect($first->can('anything.at.all'))->toBeTrue()
        ->and($second->can('anything.at.all'))->toBeTrue();

    $second->syncRoles([]);

    expect($first->can('anything.at.all'))->toBeFalse()
        ->and($second->can('anything.at.all'))->toBeFalse();
});

it('sees the persisted super-admin assignment on a fresh instance', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-fresh@example.com');
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    expect(User::query()->findOrFail($first->getKey())->can('anything.at.all'))->toBeTrue();

    $first->removeRole($role);

    expect(User::query()->findOrFail($first->getKey())->can('anything.at.all'))->toBeFalse();
});

it('keeps loaded instances fresh around team-scoped removal when teams are enabled', function (): void {
    az02bUseTeams(true);

    $first = az02bUser('az02b-teams-first@example.com');
    $second = User::query()->findOrFail($first->getKey());
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    expect($second->can('anything.at.all'))->toBeTrue();

    setPermissionsTeamId('team-a');

    // The recheck runs under a team scope: the global assignment still wins.
    expect($second->can('anything.at.all'))->toBeTrue();

    setPermissionsTeamId(null);

    $first->removeRole($role);

    expect($second->can('anything.at.all'))->toBeFalse();
});

it('reflects a revocation inside a nested scoped check when teams are disabled', function (): void {
    az02bUseTeams(false);

    $first = az02bUser('az02b-scope-first@example.com');
    $second = User::query()->findOrFail($first->getKey());
    $role = az02bSuperAdminRole();
    $first->assignRole($role);

    expect($second->can('anything.at.all'))->toBeTrue();

    $first->removeRole($role);

    $authz = app(Authz::class);

    // userCanInScope resets request state before checking; the direct
    // withScope form below does not receive the user, so the stale relation
    // is only fenced by the global-role check itself.
    $nested = $authz->withScope('team-a', fn (): bool => (bool) $second->can('anything.at.all'));

    expect($authz->userCanInScope($second, 'anything.at.all', 'team-a'))->toBeFalse()
        ->and($nested)->toBeFalse()
        ->and(getPermissionsTeamId())->toBeNull();
});

it('restores the registrar team state after a nested scoped global role check', function (): void {
    az02bUseTeams(true);

    $user = az02bUser('az02b-nested-teams@example.com');
    $role = az02bSuperAdminRole();

    setPermissionsTeamId('team-a');
    $user->assignRole($role);
    setPermissionsTeamId(null);

    $authz = app(Authz::class);
    $registrar = app(PermissionRegistrar::class);

    $inside = $authz->withScope('team-a', function () use ($user, $registrar): bool {
        $verdict = (bool) $user->can('anything.at.all');

        expect($registrar->teams)->toBeTrue()
            ->and(getPermissionsTeamId())->toBe('team-a');

        return $verdict;
    }, $user);

    expect($inside)->toBeTrue()
        ->and($registrar->teams)->toBeTrue()
        ->and(getPermissionsTeamId())->toBeNull();

    // Direct checker calls report the same fresh verdict and leave the state intact.
    expect(UserRoleChecker::hasGlobalRole($user, 'Super Admin'))->toBeTrue()
        ->and($registrar->teams)->toBeTrue();

    setPermissionsTeamId('team-a');
    $user->removeRole($role);
    setPermissionsTeamId(null);

    expect(UserRoleChecker::hasGlobalRole($user, 'Super Admin'))->toBeFalse()
        ->and((bool) $user->can('anything.at.all'))->toBeFalse();
});
