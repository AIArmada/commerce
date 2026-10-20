<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression coverage for AZ-04: scope switching must control the team
 * resolver actually bound to the permission registrar, including Spatie's
 * default resolver and custom resolvers that use the Spatie team API.
 */
function az04UseTeamResolver(string $resolver): void
{
    config()->set('permission.teams', true);
    config()->set('permission.team_resolver', $resolver);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class);

    setPermissionsTeamId(null);
}

function az04User(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-04 User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function az04GrantPermission(User $user, string $name): void
{
    $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
}

beforeEach(function (): void {
    az04UseTeamResolver(DefaultTeamResolver::class);
});

it('switches and restores the team id of the default resolver', function (): void {
    $authz = app(Authz::class);

    setPermissionsTeamId('team-a');

    $observed = $authz->withScope('team-b', fn (): int | string | null => getPermissionsTeamId());

    expect($observed)->toBe('team-b')
        ->and(getPermissionsTeamId())->toBe('team-a');

    $observedGlobal = 'unset';

    $authz->withScope(null, function () use (&$observedGlobal): void {
        $observedGlobal = getPermissionsTeamId();
    });

    expect($observedGlobal)->toBeNull()
        ->and(getPermissionsTeamId())->toBe('team-a');
});

it('scopes gate verdicts to the requested team with the default resolver', function (): void {
    $user = az04User('az04-default-gate@example.com');

    setPermissionsTeamId('team-a');
    az04GrantPermission($user, 'audit.write');
    setPermissionsTeamId(null);

    $authz = app(Authz::class);

    expect($authz->userCanInScope($user, 'audit.write', 'team-a'))->toBeTrue()
        ->and($authz->userCanInScope($user, 'audit.write', 'team-b'))->toBeFalse()
        ->and(getPermissionsTeamId())->toBeNull();

    $inside = $authz->withScope('team-a', function () use ($user): array {
        return [getPermissionsTeamId(), $user->can('audit.write')];
    }, $user);

    expect($inside)->toBe(['team-a', true])
        ->and(getPermissionsTeamId())->toBeNull();

    $grantedInB = $authz->withScope('team-b', fn (): bool => (bool) $user->can('audit.write'), $user);

    expect($grantedInB)->toBeFalse();
});

it('scopes gate verdicts through a custom resolver team id', function (): void {
    az04UseTeamResolver(Az04SwitchableTeamResolver::class);

    $user = az04User('az04-custom-gate@example.com');

    setPermissionsTeamId('custom-a');
    az04GrantPermission($user, 'audit.write');
    setPermissionsTeamId(null);

    $authz = app(Authz::class);

    expect($authz->userCanInScope($user, 'audit.write', 'custom-a'))->toBeTrue()
        ->and($authz->userCanInScope($user, 'audit.write', 'custom-b'))->toBeFalse();
});

it('switches and restores a custom resolver through the spatie team api', function (): void {
    az04UseTeamResolver(Az04SwitchableTeamResolver::class);

    $authz = app(Authz::class);

    setPermissionsTeamId('outer');

    $observed = $authz->withScope('inner', fn (): int | string | null => getPermissionsTeamId());

    expect($observed)->toBe('inner')
        ->and(getPermissionsTeamId())->toBe('outer');

    $observedGlobal = 'unset';

    $authz->withScope(null, function () use (&$observedGlobal): void {
        $observedGlobal = getPermissionsTeamId();
    });

    expect($observedGlobal)->toBeNull()
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('fails explicitly before the callback when the resolver cannot honor the scope', function (): void {
    az04UseTeamResolver(Az04ImmovableTeamResolver::class);

    $authz = app(Authz::class);
    $executed = false;

    expect(function () use ($authz, &$executed): void {
        $authz->withScope('team-b', function () use (&$executed): void {
            $executed = true;
        });
    })->toThrow(ScopeResolutionException::class);

    expect($executed)->toBeFalse()
        ->and(getPermissionsTeamId())->toBeNull();

    // userCanInScope fails closed instead of executing under the previous team.
    $user = az04User('az04-immovable@example.com');

    expect($authz->userCanInScope($user, 'audit.write', 'team-b'))->toBeFalse()
        ->and($executed)->toBeFalse();
});

it('rejects invalid scope references without touching the previous team', function (): void {
    $authz = app(Authz::class);
    $executed = false;

    setPermissionsTeamId('team-a');

    foreach ([[], '', new User] as $invalid) {
        expect(function () use ($authz, $invalid, &$executed): void {
            $authz->withScope($invalid, function () use (&$executed): void {
                $executed = true;
            });
        })->toThrow(ScopeResolutionException::class);
    }

    expect($executed)->toBeFalse()
        ->and(getPermissionsTeamId())->toBe('team-a');

    $user = az04User('az04-invalid@example.com');

    expect($authz->userCanInScope($user, 'audit.write', []))->toBeFalse()
        ->and($authz->userCanInScope($user, 'audit.write', ''))->toBeFalse()
        ->and(getPermissionsTeamId())->toBe('team-a');
});

it('restores the outer team after nested scopes and throwing callbacks', function (): void {
    $authz = app(Authz::class);

    setPermissionsTeamId('outer');

    $nested = $authz->withScope('middle', function () use ($authz): int | string | null {
        expect(getPermissionsTeamId())->toBe('middle');

        return $authz->withScope('inner', fn (): int | string | null => getPermissionsTeamId());
    });

    expect($nested)->toBe('inner')
        ->and(getPermissionsTeamId())->toBe('outer');

    expect(function () use ($authz): void {
        $authz->withScope('middle', function (): never {
            throw new RuntimeException('az04-callback-boom');
        });
    })->toThrow(RuntimeException::class, 'az04-callback-boom');

    expect(getPermissionsTeamId())->toBe('outer');
});

it('resolves default-resolver scope references to team ids', function (): void {
    $user = az04User('az04-resolve@example.com');
    $authz = app(Authz::class);

    expect($authz->resolveScopeId(null))->toBeNull()
        ->and($authz->resolveScopeId('team-x'))->toBe('team-x')
        ->and($authz->resolveScopeId(42))->toBe(42)
        ->and($authz->resolveScopeId($user))->toBe($user->getKey());

    expect(fn () => $authz->resolveScopeId(new User))->toThrow(ScopeResolutionException::class);
});

final class Az04SwitchableTeamResolver implements PermissionsTeamResolver
{
    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }
}

final class Az04ImmovableTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int | string | null
    {
        return null;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        // Intentionally ignores the requested team: this resolver cannot honor a scope.
    }
}
