<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Support\OwnerContextTeamResolver;
use Illuminate\Database\Eloquent\Model;

it('resolves the current owner from the resolver and supports overrides', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly User $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(OwnerContext::resolve()?->getKey())->toBe($ownerA->getKey());

    OwnerContext::setForRequest(null);

    expect(OwnerContext::resolve())->toBeNull();

    app('request')->attributes->remove(OwnerContext::REQUEST_KEY);

    $result = OwnerContext::withOwner($ownerB, function () use ($ownerB): string {
        expect(OwnerContext::resolve()?->getKey())->toBe($ownerB->getKey());

        return 'ok';
    });

    expect($result)->toBe('ok')
        ->and(OwnerContext::resolve()?->getKey())->toBe($ownerA->getKey());
});

it('builds owner instances from type and id', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner C',
        'email' => 'owner-c@example.com',
        'password' => 'secret',
    ]);

    $resolved = OwnerContext::fromTypeAndId($owner->getMorphClass(), $owner->getKey());

    expect($resolved)->toBeInstanceOf(User::class)
        ->and($resolved?->getKey())->toBe($owner->getKey());
});

it('returns null for null owner payloads', function (): void {
    expect(OwnerContext::fromTypeAndId(null, null))->toBeNull()
        ->and(OwnerContext::fromTypeAndId(User::class, null))->toBeNull()
        ->and(OwnerContext::fromTypeAndId(null, '123'))->toBeNull();
});

it('throws for empty-string owner payloads', function (): void {
    OwnerContext::fromTypeAndId('', null);
})->throws(InvalidArgumentException::class);

it('throws when the owner id is an empty string', function (): void {
    OwnerContext::fromTypeAndId(User::class, '');
})->throws(InvalidArgumentException::class);

it('throws when owner type cannot be resolved', function (): void {
    OwnerContext::fromTypeAndId('MissingOwnerType', '123');
})->throws(InvalidArgumentException::class);

it('bridges spatie team resolution to the owner context', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Team',
        'email' => 'owner-team@example.com',
        'password' => 'secret',
    ]);

    $resolver = new OwnerContextTeamResolver;

    OwnerContext::setForRequest($owner);

    expect($resolver->getPermissionsTeamId())->toBe($owner->getKey());

    app('request')->attributes->remove(OwnerContext::REQUEST_KEY);

    $resolver->setPermissionsTeamId($owner);

    expect(OwnerContext::resolve()?->getKey())->toBe($owner->getKey());

    app('request')->attributes->remove(OwnerContext::REQUEST_KEY);

    $resolver->setPermissionsTeamId($owner->getKey());

    expect(OwnerContext::resolve()?->getKey())->toBe($owner->getKey());
});

it('throws when setForRequest is called outside an active HTTP request', function (): void {
    $originalRequest = app('request');

    app()->offsetUnset('request');

    try {
        expect(fn () => OwnerContext::setForRequest(null))
            ->toThrow(RuntimeException::class, 'may only be used during an active HTTP request');
    } finally {
        app()->instance('request', $originalRequest);
    }
});
