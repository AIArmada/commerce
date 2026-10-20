<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Support\AuthzScopeContext;
use AIArmada\Authz\Support\AuthzScopeTeamResolver;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression coverage for AZ-05: userCanInScope() classifies its own scope
 * resolution/setup rejection before running the ability callback, returns
 * false only for that rejection, and lets policy/callback exceptions
 * propagate unchanged in every resolver mode.
 */
function az05UseResolver(string $resolver, bool $scopesEnabled = false): void
{
    config()->set('authz.scopes.enabled', $scopesEnabled);
    config()->set('permission.team_resolver', $resolver);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class);

    setPermissionsTeamId(null);
    AuthzScopeContext::clear();
}

function az05User(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-05 User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

final class Az05SpyAuthorizable implements Authorizable
{
    public int $checks = 0;

    public function can($abilities, $arguments = []): bool
    {
        $this->checks++;

        return true;
    }
}

final class Az05SelectiveTeamResolver implements PermissionsTeamResolver
{
    public const string REJECTED_TEAM = 'az05-rejected-team';

    private int | string | null $teamId = null;

    public function getPermissionsTeamId(): int | string | null
    {
        return $this->teamId;
    }

    public function setPermissionsTeamId(int | string | Model | null $id): void
    {
        $value = $id instanceof Model ? $id->getKey() : $id;

        if ($value === self::REJECTED_TEAM) {
            return;
        }

        $this->teamId = $value;
    }
}

it('propagates a policy ScopeResolutionException in generic resolver mode', function (): void {
    az05UseResolver(DefaultTeamResolver::class);

    $user = az05User('az05-generic-policy@example.com');

    Gate::define('orders.view', static function (): never {
        throw new ScopeResolutionException('az05-generic-policy-boom');
    });

    setPermissionsTeamId('outer');

    expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', 'team-b'))
        ->toThrow(ScopeResolutionException::class, 'az05-generic-policy-boom');

    expect(getPermissionsTeamId())->toBe('outer');
});

it('propagates a non-resolver policy failure in generic resolver mode', function (): void {
    az05UseResolver(DefaultTeamResolver::class);

    $user = az05User('az05-generic-runtime@example.com');

    Gate::define('orders.view', static function (): never {
        throw new RuntimeException('az05-generic-runtime-boom');
    });

    setPermissionsTeamId('outer');

    expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', 'team-b'))
        ->toThrow(RuntimeException::class, 'az05-generic-runtime-boom');

    expect(getPermissionsTeamId())->toBe('outer');
});

it('fails closed without invoking the ability check when the resolver rejects the scope', function (): void {
    az05UseResolver(Az05SelectiveTeamResolver::class);

    $user = new Az05SpyAuthorizable;

    setPermissionsTeamId('team-a');

    $result = app(Authz::class)->userCanInScope($user, 'orders.view', Az05SelectiveTeamResolver::REJECTED_TEAM);

    expect($result)->toBeFalse()
        ->and($user->checks)->toBe(0)
        ->and(getPermissionsTeamId())->toBe('team-a');
});

it('propagates a ScopeResolutionException thrown by a withScope callback', function (): void {
    az05UseResolver(DefaultTeamResolver::class);

    setPermissionsTeamId('outer');

    expect(fn () => app(Authz::class)->withScope('team-a', static function (): never {
        throw new ScopeResolutionException('az05-withscope-boom');
    }))->toThrow(ScopeResolutionException::class, 'az05-withscope-boom');

    expect(getPermissionsTeamId())->toBe('outer');
});

it('propagates a policy ScopeResolutionException in owner-context resolver mode', function (): void {
    az05UseResolver(OwnerContextTeamResolver::class);

    $user = az05User('az05-owner-policy@example.com');
    $owner = az05User('az05-owner-scope@example.com');

    Gate::define('orders.view', static function (): never {
        throw new ScopeResolutionException('az05-owner-policy-boom');
    });

    OwnerContext::withOwner($owner, function () use ($user, $owner): void {
        expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', $owner))
            ->toThrow(ScopeResolutionException::class, 'az05-owner-policy-boom');

        expect(OwnerContext::resolve()?->getKey())->toBe($owner->getKey());
    });
});

it('fails closed without invoking the ability check when an owner scope cannot resolve', function (): void {
    az05UseResolver(OwnerContextTeamResolver::class);

    $user = new Az05SpyAuthorizable;
    $owner = az05User('az05-owner-context@example.com');

    OwnerContext::withOwner($owner, function () use ($user, $owner): void {
        config()->set('commerce-support.owner.team_type', null);

        $result = app(Authz::class)->userCanInScope($user, 'orders.view', 'owner-123');

        expect($result)->toBeFalse()
            ->and($user->checks)->toBe(0)
            ->and(OwnerContext::resolve()?->getKey())->toBe($owner->getKey());
    });
});

it('propagates a policy ScopeResolutionException in authz-scope resolver mode', function (): void {
    az05UseResolver(AuthzScopeTeamResolver::class, scopesEnabled: true);

    $user = az05User('az05-authz-policy@example.com');
    $target = az05User('az05-authz-target@example.com');

    Gate::define('orders.view', static function (): never {
        throw new ScopeResolutionException('az05-authz-policy-boom');
    });

    AuthzScopeContext::clear();

    expect(fn () => app(Authz::class)->userCanInScope($user, 'orders.view', $target))
        ->toThrow(ScopeResolutionException::class, 'az05-authz-policy-boom');

    expect(AuthzScopeContext::hasOverride())->toBeFalse();
});

it('fails closed and preserves the scope override when an authz scope cannot resolve', function (): void {
    az05UseResolver(AuthzScopeTeamResolver::class, scopesEnabled: true);
    config()->set('authz.scopes.auto_create', false);

    $user = new Az05SpyAuthorizable;
    $target = az05User('az05-authz-unresolved@example.com');

    AuthzScopeContext::set('scope-entry');

    $result = app(Authz::class)->userCanInScope($user, 'orders.view', $target);

    expect($result)->toBeFalse()
        ->and($user->checks)->toBe(0)
        ->and(AuthzScopeContext::resolve())->toBe('scope-entry')
        ->and(AuthzScopeContext::hasOverride())->toBeTrue();
});
