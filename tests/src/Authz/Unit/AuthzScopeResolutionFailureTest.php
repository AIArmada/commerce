<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Models\AuthzScope;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Support\AuthzScopeContext;
use AIArmada\Authz\Support\AuthzScopeTeamResolver;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Regression coverage for AZ-01 in the Authz scope resolver mode
 * (authz.scopes.enabled + AuthzScopeTeamResolver).
 */
beforeEach(function (): void {
    config()->set('authz.scopes.enabled', true);
    config()->set('permission.team_resolver', AuthzScopeTeamResolver::class);

    app()->forgetInstance(PermissionRegistrar::class);
    app(PermissionRegistrar::class);

    AuthzScopeContext::clear();
});

function authzScopeResolutionUser(string $email): User
{
    return User::query()->create([
        'name' => 'Authz Scope Resolution User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

it('denies a scoped check when the model scope cannot resolve and a global permission exists', function (): void {
    config()->set('authz.scopes.auto_create', false);

    $user = authzScopeResolutionUser('az01-global-permission@example.com');
    $target = authzScopeResolutionUser('az01-unresolved-target@example.com');

    $user->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));

    expect($user->can('orders.view'))->toBeTrue()
        ->and(app(Authz::class)->userCanInScope($user, 'orders.view', $target))->toBeFalse();
});

it('throws for a non-null scope that cannot resolve and never executes the callback', function (): void {
    config()->set('authz.scopes.auto_create', false);

    $target = authzScopeResolutionUser('az01-throwing-target@example.com');
    $executed = false;

    expect(function () use ($target, &$executed): void {
        app(Authz::class)->withScope($target, function () use (&$executed): void {
            $executed = true;
        });
    })->toThrow(ScopeResolutionException::class);

    expect($executed)->toBeFalse();
});

it('rejects unsaved models and invalid scope values', function (): void {
    $authz = app(Authz::class);

    expect(fn () => $authz->withScope(new User, fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);

    expect(fn () => $authz->withScope([], fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);

    expect(fn () => $authz->withScope('', fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);
});

it('rejects an Authz scope record that no longer exists', function (): void {
    $target = authzScopeResolutionUser('az01-deleted-scope@example.com');

    $scope = AuthzScope::query()->create([
        'scopeable_type' => $target->getMorphClass(),
        'scopeable_id' => $target->getKey(),
        'label' => 'Deleted Authz Scope',
    ]);

    $scope->delete();

    expect(fn () => app(Authz::class)->withScope($scope, fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);
});

it('fails the team resolver when a non-null model scope cannot resolve', function (): void {
    config()->set('authz.scopes.auto_create', false);

    $target = authzScopeResolutionUser('az01-resolver-target@example.com');
    $resolver = new AuthzScopeTeamResolver;

    expect(fn () => $resolver->setPermissionsTeamId($target))
        ->toThrow(ScopeResolutionException::class);

    expect(AuthzScopeContext::hasOverride())->toBeFalse();
});

it('keeps explicit global checks and valid scope checks working', function (): void {
    $user = authzScopeResolutionUser('az01-valid@example.com');
    $target = authzScopeResolutionUser('az01-valid-target@example.com');

    $user->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));

    $scope = AuthzScope::query()->create([
        'scopeable_type' => $target->getMorphClass(),
        'scopeable_id' => $target->getKey(),
        'label' => 'Valid Authz Scope',
    ]);

    setPermissionsTeamId($scope->getKey());
    $user->givePermissionTo(Permission::findOrCreate('orders.update', 'web'));
    setPermissionsTeamId(null);

    $authz = app(Authz::class);

    expect($authz->resolveScopeId($scope->getKey()))->toBe($scope->getKey())
        ->and($authz->userCanInScope($user, 'orders.view', null))->toBeTrue()
        ->and($authz->userCanInScope($user, 'orders.update', $scope->getKey()))->toBeTrue()
        ->and($authz->userCanInScope($user, 'orders.update', $target))->toBeTrue()
        ->and($authz->userCanInScope($user, 'orders.delete', $scope->getKey()))->toBeFalse();
});

it('restores the entry scope identity and override flag on every exit path', function (): void {
    $authz = app(Authz::class);

    AuthzScopeContext::clear();
    $authz->withScope('scope-temp', fn (): ?string => null);

    expect(AuthzScopeContext::hasOverride())->toBeFalse();

    AuthzScopeContext::set('scope-entry');
    $authz->withScope('scope-temp', fn (): ?string => null);

    expect(AuthzScopeContext::resolve())->toBe('scope-entry')
        ->and(AuthzScopeContext::hasOverride())->toBeTrue();

    AuthzScopeContext::set(null);
    $authz->withScope('scope-temp', fn (): ?string => null);

    expect(AuthzScopeContext::resolve())->toBeNull()
        ->and(AuthzScopeContext::hasOverride())->toBeTrue();
});

it('restores the previous scope after nested calls and throwing callbacks', function (): void {
    $authz = app(Authz::class);

    AuthzScopeContext::clear();

    $authz->withScope('scope-outer', function () use ($authz): void {
        $authz->withScope('scope-inner', fn (): ?string => null);
    });

    expect(AuthzScopeContext::hasOverride())->toBeFalse();

    expect(fn () => $authz->withScope('scope-outer', function (): never {
        throw new RuntimeException('callback-boom');
    }))->toThrow(RuntimeException::class, 'callback-boom');

    expect(AuthzScopeContext::hasOverride())->toBeFalse();
});
