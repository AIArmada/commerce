<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContextTeamResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

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
    expect(OwnerContext::fromTypeAndId(null, null))->toBeNull();
    expect(fn () => OwnerContext::fromTypeAndId(User::class, null))->toThrow(InvalidArgumentException::class);
    expect(fn () => OwnerContext::fromTypeAndId(null, '123'))->toThrow(InvalidArgumentException::class);
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

it('resolves persisted owners from type and id or fails', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner OrFail',
        'email' => 'owner-orfail@example.com',
        'password' => 'secret',
    ]);

    $resolved = OwnerContext::fromTypeAndIdOrFail($owner->getMorphClass(), $owner->getKey());

    expect($resolved)->toBeInstanceOf(User::class)
        ->and($resolved?->getKey())->toBe($owner->getKey())
        ->and($resolved?->exists)->toBeTrue()
        ->and(OwnerContext::fromTypeAndIdOrFail(null, null))->toBeNull();
});

it('fails for orphaned owner tuples', function (): void {
    OwnerContext::fromTypeAndIdOrFail(User::class, 999999);
})->throws(ModelNotFoundException::class);

it('fails for half-null owner tuples', function (): void {
    OwnerContext::fromTypeAndIdOrFail(User::class, null);
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

it('restores owner overrides when scoped callbacks throw', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner Exception A',
        'email' => 'owner-exception-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner Exception B',
        'email' => 'owner-exception-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $ownerB): void {
        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();

        expect(function () use ($ownerB): void {
            OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
                expect(OwnerContext::resolve()?->is($ownerB))->toBeTrue();

                throw new RuntimeException('owner scope failed');
            });
        })->toThrow(RuntimeException::class, 'owner scope failed');

        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();
    });

    expect(OwnerContext::hasOverride())->toBeFalse();
});

it('restores fallback owner overrides outside http requests when callbacks throw', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Fallback Exception',
        'email' => 'owner-fallback-exception@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $originalRequest = app('request');

    app()->offsetUnset('request');

    try {
        expect(function () use ($owner): void {
            OwnerContext::withOwner($owner, function () use ($owner): void {
                expect(OwnerContext::resolve()?->is($owner))->toBeTrue();

                throw new RuntimeException('fallback owner scope failed');
            });
        })->toThrow(RuntimeException::class, 'fallback owner scope failed');

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and(OwnerContext::resolve())->toBeNull();
    } finally {
        app()->instance('request', $originalRequest);
    }
});

it('keeps request owner state isolated per http request instance', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner Request A',
        'email' => 'owner-request-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner Request B',
        'email' => 'owner-request-b@example.com',
        'password' => 'secret',
    ]);

    $originalRequest = app('request');
    $requestA = Request::create('/owner-a');
    $requestB = Request::create('/owner-b');

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    try {
        app()->instance('request', $requestA);
        OwnerContext::setForRequest($ownerA);

        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();

        app()->instance('request', $requestB);

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and(OwnerContext::resolve())->toBeNull();

        OwnerContext::setForRequest($ownerB);

        expect(OwnerContext::resolve()?->is($ownerB))->toBeTrue();

        app()->instance('request', $requestA);

        expect(OwnerContext::resolve()?->is($ownerA))->toBeTrue();
    } finally {
        app()->instance('request', $originalRequest);
    }
});

it('does not expose an HTTP owner override to another request without owner state', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Isolated Request',
        'email' => 'owner-isolated-request@example.com',
        'password' => 'secret',
    ]);

    $originalRequest = app('request');
    $requestA = Request::create('/owner-a');
    $requestB = Request::create('/owner-b');

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    try {
        app()->instance('request', $requestA);

        OwnerContext::withOwner($owner, function () use ($requestA, $requestB): void {
            app()->instance('request', $requestB);

            expect(OwnerContext::hasOverride())->toBeFalse()
                ->and(OwnerContext::resolve())->toBeNull();

            app()->instance('request', $requestA);
        });
    } finally {
        app()->instance('request', $originalRequest);
    }
});

it('restores scope state onto the request captured at entry when the request is swapped mid-callback', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner Swap Restore',
        'email' => 'owner-swap-restore@example.com',
        'password' => 'secret',
    ]);

    $originalRequest = app('request');
    $requestA = Request::create('/owner-a');
    $requestB = Request::create('/owner-b');

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    try {
        app()->instance('request', $requestA);

        OwnerContext::withOwner($owner, function () use ($requestB): void {
            app()->instance('request', $requestB);
        });

        // The callback left the swapped-in request active; the finally must
        // still have restored state onto the captured request only.
        expect($requestB->attributes->has(OwnerContext::REQUEST_KEY))->toBeFalse()
            ->and($requestA->attributes->get(OwnerContext::REQUEST_KEY))->toBe([
                'hasOverride' => false,
                'override' => null,
            ])
            ->and(OwnerContext::hasOverride())->toBeFalse();
    } finally {
        app()->instance('request', $originalRequest);
    }
});

it('applies non-HTTP team id changes to the active scoped override only', function (): void {
    config()->set('commerce-support.owner.team_type', User::class);

    $ownerA = User::query()->create([
        'name' => 'Owner Team A',
        'email' => 'owner-team-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner Team B',
        'email' => 'owner-team-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $originalRequest = app('request');

    app()->offsetUnset('request');

    try {
        $resolver = new OwnerContextTeamResolver;

        // No active scope: the call is ignored instead of crashing or
        // persisting process-wide owner state.
        $resolver->setPermissionsTeamId($ownerA->getKey());

        expect($resolver->getPermissionsTeamId())->toBeNull()
            ->and(OwnerContext::hasOverride())->toBeFalse();

        OwnerContext::withOwner($ownerA, function () use ($resolver, $ownerB): void {
            $resolver->setPermissionsTeamId($ownerB->getKey());

            expect($resolver->getPermissionsTeamId())->toBe($ownerB->getKey());
        });

        expect($resolver->getPermissionsTeamId())->toBeNull();
    } finally {
        app()->instance('request', $originalRequest);
        OwnerContext::flushState();
    }
});
