<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Exceptions\ScopeRestorationException;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression coverage for AZ-06: a rejected generic team setup must not leak
 * the requested scope. Setter or getter failures after the resolver has
 * already changed state restore the captured previous team and invalidate
 * request-local permission state before the original error propagates; a
 * rollback the resolver cannot honor fails loud instead of continuing under
 * an unknown scope.
 */
function az06UseResolver(string $resolver): void
{
    config()->set('permission.teams', true);
    config()->set('permission.team_resolver', $resolver);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class);

    setPermissionsTeamId(null);
}

function az06User(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-06 User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

beforeEach(function (): void {
    az06UseResolver(DefaultTeamResolver::class);
});

final class Az06SpyAuthorizable implements Authorizable
{
    public int $checks = 0;

    public function can($abilities, $arguments = []): bool
    {
        $this->checks++;

        return true;
    }
}

final class Az06MutatingRejectingTeamResolver implements PermissionsTeamResolver
{
    public const string REJECTED_TEAM = 'inner-rejected';

    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $value = $id instanceof Model ? $id->getKey() : $id;

        $this->teamId = $value;

        if ($value === self::REJECTED_TEAM) {
            throw ScopeResolutionException::forScope($value, 'az06-rejected-team-boom');
        }
    }
}

final class Az06DenyingGetterTeamResolver implements PermissionsTeamResolver
{
    public const string REJECTED_TEAM = 'inner-rejected';

    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        if ($this->teamId === self::REJECTED_TEAM) {
            throw ScopeResolutionException::forScope($this->teamId, 'az06-getter-boom');
        }

        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }
}

final class Az06FailingGetterTeamResolver implements PermissionsTeamResolver
{
    public const string REJECTED_TEAM = 'inner-rejected';

    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        if ($this->teamId === self::REJECTED_TEAM) {
            throw new RuntimeException('az06-getter-runtime-boom');
        }

        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }
}

final class Az06StickyRejectingTeamResolver implements PermissionsTeamResolver
{
    public const string REJECTED_TEAM = 'inner-rejected';

    private int | string | null $teamId = null;

    private bool $stuck = false;

    public function getPermissionsTeamId(): int | string | null
    {
        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        if ($this->stuck) {
            return;
        }

        $value = $id instanceof Model ? $id->getKey() : $id;

        $this->teamId = $value;

        if ($value === self::REJECTED_TEAM) {
            $this->stuck = true;

            throw ScopeResolutionException::forScope($value, 'az06-sticky-rejection-boom');
        }
    }
}

final class Az06RejectingRestoreTeamResolver implements PermissionsTeamResolver
{
    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $value = $id instanceof Model ? $id->getKey() : $id;

        if ($this->teamId === 'inner' && $value === 'outer') {
            return;
        }

        $this->teamId = $value;
    }
}

it('restores the previous team when a withScope setup mutates then throws', function (): void {
    az06UseResolver(Az06MutatingRejectingTeamResolver::class);

    $authz = app(Authz::class);
    $calls = 0;

    setPermissionsTeamId('outer');

    expect(function () use ($authz, &$calls): void {
        $authz->withScope(Az06MutatingRejectingTeamResolver::REJECTED_TEAM, function () use (&$calls): void {
            $calls++;
        });
    })->toThrow(ScopeResolutionException::class, 'az06-rejected-team-boom');

    expect($calls)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('restores the previous team and skips the ability check when userCanInScope setup mutates then throws', function (): void {
    az06UseResolver(Az06MutatingRejectingTeamResolver::class);

    $user = new Az06SpyAuthorizable;

    setPermissionsTeamId('outer');

    $result = app(Authz::class)->userCanInScope($user, 'orders.view', Az06MutatingRejectingTeamResolver::REJECTED_TEAM);

    expect($result)->toBeFalse()
        ->and($user->checks)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('restores the previous team when verification fails after a stored switch', function (): void {
    az06UseResolver(Az06DenyingGetterTeamResolver::class);

    $authz = app(Authz::class);
    $calls = 0;

    setPermissionsTeamId('outer');

    expect(function () use ($authz, &$calls): void {
        $authz->withScope(Az06DenyingGetterTeamResolver::REJECTED_TEAM, function () use (&$calls): void {
            $calls++;
        });
    })->toThrow(ScopeResolutionException::class, 'az06-getter-boom');

    expect($calls)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('outer');

    $user = new Az06SpyAuthorizable;

    expect($authz->userCanInScope($user, 'orders.view', Az06DenyingGetterTeamResolver::REJECTED_TEAM))->toBeFalse()
        ->and($user->checks)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('propagates a non-scope verification failure after restoring the previous team', function (): void {
    az06UseResolver(Az06FailingGetterTeamResolver::class);

    $authz = app(Authz::class);
    $user = new Az06SpyAuthorizable;

    setPermissionsTeamId('outer');

    expect(fn () => $authz->userCanInScope($user, 'orders.view', Az06FailingGetterTeamResolver::REJECTED_TEAM))
        ->toThrow(RuntimeException::class, 'az06-getter-runtime-boom');

    expect($user->checks)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('restores the enclosing team when a nested rejected scope fails setup', function (): void {
    az06UseResolver(Az06MutatingRejectingTeamResolver::class);

    $authz = app(Authz::class);
    $observedInside = 'unset';

    setPermissionsTeamId('outer');

    $authz->withScope('middle', function () use ($authz, &$observedInside): void {
        try {
            $authz->withScope(Az06MutatingRejectingTeamResolver::REJECTED_TEAM, fn (): string => 'never');
        } catch (ScopeResolutionException) {
            // The rejected inner scope is expected to surface to the caller.
        }

        $observedInside = getPermissionsTeamId();
    });

    expect($observedInside)->toBe('middle')
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('invalidates request-local permission state after a rejected switch', function (): void {
    az06UseResolver(Az06MutatingRejectingTeamResolver::class);

    $user = az06User('az06-relations@example.com');

    setPermissionsTeamId('outer');

    $user->load('roles');
    $user->load('permissions');

    expect($user->relationLoaded('roles'))->toBeTrue()
        ->and($user->relationLoaded('permissions'))->toBeTrue();

    expect(app(Authz::class)->userCanInScope($user, 'orders.view', Az06MutatingRejectingTeamResolver::REJECTED_TEAM))->toBeFalse();

    expect($user->relationLoaded('roles'))->toBeFalse()
        ->and($user->relationLoaded('permissions'))->toBeFalse()
        ->and(getPermissionsTeamId())->toBe('outer');
});

it('still restores the entry team after successful setup and policy exceptions', function (): void {
    az06UseResolver(DefaultTeamResolver::class);

    $authz = app(Authz::class);
    $user = az06User('az06-successful@example.com');

    setPermissionsTeamId('outer');

    $observed = $authz->withScope('team-a', fn (): int | string | null => getPermissionsTeamId());

    expect($observed)->toBe('team-a')
        ->and(getPermissionsTeamId())->toBe('outer');

    Gate::define('orders.view', static function (): never {
        throw new RuntimeException('az06-policy-runtime-boom');
    });

    expect(fn () => $authz->userCanInScope($user, 'orders.view', 'team-a'))
        ->toThrow(RuntimeException::class, 'az06-policy-runtime-boom');

    expect(getPermissionsTeamId())->toBe('outer');

    Gate::define('orders.update', static function (): never {
        throw new ScopeResolutionException('az06-policy-scope-boom');
    });

    expect(fn () => $authz->userCanInScope($user, 'orders.update', 'team-a'))
        ->toThrow(ScopeResolutionException::class, 'az06-policy-scope-boom');

    expect(getPermissionsTeamId())->toBe('outer');
});

it('fails loud when a rejected setup cannot be rolled back', function (): void {
    az06UseResolver(Az06StickyRejectingTeamResolver::class);

    $authz = app(Authz::class);
    $calls = 0;

    setPermissionsTeamId('outer');

    $restorationException = null;

    try {
        $authz->withScope(Az06StickyRejectingTeamResolver::REJECTED_TEAM, function () use (&$calls): void {
            $calls++;
        });
    } catch (ScopeRestorationException $restorationException) {
        // Expected: the rollback cannot be honored.
    }

    expect($restorationException)->toBeInstanceOf(ScopeRestorationException::class)
        ->and($restorationException?->getPrevious())->toBeInstanceOf(ScopeResolutionException::class)
        ->and($restorationException?->getPrevious()?->getMessage())->toContain('az06-sticky-rejection-boom')
        ->and($calls)->toBe(0);
});

it('never converts a failed rollback into a denied scope', function (): void {
    az06UseResolver(Az06StickyRejectingTeamResolver::class);

    $user = new Az06SpyAuthorizable;

    setPermissionsTeamId('outer');

    expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', Az06StickyRejectingTeamResolver::REJECTED_TEAM))
        ->toThrow(ScopeRestorationException::class);

    expect($user->checks)->toBe(0);
});

it('fails loud when the entry team cannot be restored after scoped work', function (): void {
    az06UseResolver(Az06RejectingRestoreTeamResolver::class);

    $authz = app(Authz::class);
    $calls = 0;

    setPermissionsTeamId('outer');

    expect(function () use ($authz, &$calls): void {
        $authz->withScope('inner', function () use (&$calls): bool {
            $calls++;

            return true;
        });
    })->toThrow(ScopeRestorationException::class);

    expect($calls)->toBe(1);
});

it('fails loud when the entry team cannot be restored after an ability check', function (): void {
    az06UseResolver(Az06RejectingRestoreTeamResolver::class);

    $user = new Az06SpyAuthorizable;

    setPermissionsTeamId('outer');

    expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', 'inner'))
        ->toThrow(ScopeRestorationException::class);

    expect($user->checks)->toBe(1);
});

it('preserves a callback failure when the entry team cannot be restored', function (): void {
    az06UseResolver(Az06RejectingRestoreTeamResolver::class);

    setPermissionsTeamId('outer');

    $restorationException = null;

    try {
        app(Authz::class)->withScope('inner', static function (): never {
            throw new RuntimeException('az06-callback-boom');
        });
    } catch (ScopeRestorationException $restorationException) {
        // Expected: the rollback cannot be honored.
    }

    expect($restorationException)->toBeInstanceOf(ScopeRestorationException::class)
        ->and($restorationException?->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and($restorationException?->getPrevious()?->getMessage())->toBe('az06-callback-boom');
});
