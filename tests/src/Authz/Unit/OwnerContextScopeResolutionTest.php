<?php

declare(strict_types=1);

use AIArmada\Authz\Authz;
use AIArmada\Authz\Exceptions\ScopeResolutionException;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

/**
 * Regression coverage for AZ-01 in the owner-context team resolver mode
 * (permission.teams + OwnerContextTeamResolver).
 */
beforeEach(function (): void {
    app('request')->attributes->remove(OwnerContext::REQUEST_KEY);
});

function ownerContextScopeUser(string $email): User
{
    return User::query()->create([
        'name' => 'Owner Context Scope User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

it('denies invalid scope values without changing the active owner context', function (): void {
    $owner = ownerContextScopeUser('az01-owner-invalid@example.com');

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $authz = app(Authz::class);
        $executed = false;

        expect(function () use ($authz, &$executed): void {
            $authz->withScope([], function () use (&$executed): void {
                $executed = true;
            });
        })->toThrow(ScopeResolutionException::class);

        expect($executed)->toBeFalse();

        expect(OwnerContext::resolve()?->getKey())->toBe($owner->getKey());
    });
});

it('rejects unsaved models and missing team type configuration', function (): void {
    $authz = app(Authz::class);

    expect(fn () => $authz->withScope(new User, fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);

    config()->set('commerce-support.owner.team_type', null);

    expect(fn () => $authz->withScope('owner-123', fn (): string => 'ran'))
        ->toThrow(ScopeResolutionException::class);

    expect($authz->userCanInScope(
        ownerContextScopeUser('az01-owner-fail-closed@example.com'),
        'orders.view',
        'owner-123',
    ))->toBeFalse();
});

it('scopes checks to the requested owner instead of an Authz scope', function (): void {
    $user = ownerContextScopeUser('az01-owner-check@example.com');
    $ownerA = ownerContextScopeUser('az01-owner-a@example.com');
    $ownerB = ownerContextScopeUser('az01-owner-b@example.com');

    setPermissionsTeamId($ownerA->getKey());
    $user->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));
    setPermissionsTeamId(null);

    $authz = app(Authz::class);

    OwnerContext::withOwner($ownerA, function () use ($authz, $user, $ownerA, $ownerB): void {
        expect($authz->userCanInScope($user, 'orders.view', $ownerA))->toBeTrue()
            ->and($authz->userCanInScope($user, 'orders.view', $ownerB))->toBeFalse()
            ->and(OwnerContext::resolve()?->getKey())->toBe($ownerA->getKey());
    });

    expect($authz->resolveScopeId($ownerA))->toBe($ownerA->getKey())
        ->and($authz->userCanInScope($user, 'orders.view', $ownerA->getKey()))->toBeTrue();
});

it('keeps explicit global owner scope working', function (): void {
    $user = ownerContextScopeUser('az01-owner-global@example.com');

    OwnerContext::setForRequest(null);
    $user->givePermissionTo(Permission::findOrCreate('orders.view', 'web'));

    expect(app(Authz::class)->userCanInScope($user, 'orders.view', null))->toBeTrue();
});

it('restores the owner context after throwing callbacks', function (): void {
    $ownerA = ownerContextScopeUser('az01-owner-restore-a@example.com');
    $ownerB = ownerContextScopeUser('az01-owner-restore-b@example.com');

    $authz = app(Authz::class);

    OwnerContext::withOwner($ownerA, function () use ($authz, $ownerA, $ownerB): void {
        expect(fn () => $authz->withScope($ownerB, function (): never {
            throw new RuntimeException('owner-callback-boom');
        }))->toThrow(RuntimeException::class, 'owner-callback-boom');

        expect(OwnerContext::resolve()?->getKey())->toBe($ownerA->getKey());
    });
});
