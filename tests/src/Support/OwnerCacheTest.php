<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Support\Facades\Cache;

describe('OwnerCache', function (): void {
    beforeEach(function (): void {
        Cache::flush();
    });

    it('builds owner-scoped cache keys', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'test-owner';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        $key = OwnerCache::key($owner, 'cart.summary');

        expect($key)->toStartWith('owner:')
            ->and($key)->toContain(':cart.summary')
            ->and($key)->toMatch('/^owner:[a-f0-9]{64}:cart\.summary$/');
    });

    it('builds global cache keys for null owner', function (): void {
        $key = OwnerCache::key(null, 'config.defaults');

        expect($key)->toBe('owner:' . OwnerScopeKey::GLOBAL . ':config.defaults');
    });

    it('rejects empty logical keys', function (): void {
        expect(fn () => OwnerCache::key(null, ''))
            ->toThrow(InvalidArgumentException::class, 'cannot be empty');
    });

    it('rejects keys with colons', function (): void {
        expect(fn () => OwnerCache::key(null, 'cart:items'))
            ->toThrow(InvalidArgumentException::class, 'cannot contain colons');
    });

    it('rejects objects that are not owner-scope compatible', function (): void {
        expect(fn () => OwnerCache::key(new stdClass, 'cart.summary'))
            ->toThrow(TypeError::class);
    });

    it('stores and retrieves owner-scoped cache values', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-123';
            }
        };

        OwnerCache::put($owner, 'user.theme', 'dark', now()->addHour());

        expect(OwnerCache::get($owner, 'user.theme'))->toBe('dark');
    });

    it('returns default when owner-scoped key not found', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-456';
            }
        };

        $result = OwnerCache::get($owner, 'nonexistent', 'default-value');

        expect($result)->toBe('default-value');
    });

    it('remembers values with callback', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-789';
            }
        };

        $callCount = 0;

        $value1 = OwnerCache::remember($owner, 'expensive', now()->addHour(), function () use (&$callCount) {
            $callCount++;

            return 'expensive-result';
        });

        $value2 = OwnerCache::remember($owner, 'expensive', now()->addHour(), function () use (&$callCount) {
            $callCount++;

            return 'should-not-run';
        });

        expect($value1)->toBe('expensive-result')
            ->and($value2)->toBe('expensive-result')
            ->and($callCount)->toBe(1); // Callback only ran once
    });

    it('forgets owner-scoped cache keys', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-del';
            }
        };

        OwnerCache::put($owner, 'test.key', 'value');

        expect(OwnerCache::get($owner, 'test.key'))->toBe('value');

        OwnerCache::forget($owner, 'test.key');

        expect(OwnerCache::get($owner, 'test.key'))->toBeNull();
    });

    it('prevents cache bleed between different owners', function (): void {
        $owner1 = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-1';
            }
        };

        $owner2 = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-2';
            }
        };

        OwnerCache::put($owner1, 'config', 'value-1');
        OwnerCache::put($owner2, 'config', 'value-2');

        expect(OwnerCache::get($owner1, 'config'))->toBe('value-1')
            ->and(OwnerCache::get($owner2, 'config'))->toBe('value-2');
    });

    it('isolates global and owner caches', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-iso';
            }
        };

        OwnerCache::put(null, 'shared', 'global-value');
        OwnerCache::put($owner, 'shared', 'owner-value');

        expect(OwnerCache::get(null, 'shared'))->toBe('global-value')
            ->and(OwnerCache::get($owner, 'shared'))->toBe('owner-value');
    });

    it('forgetOwner is safe on stores without tag support', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-noop';
            }
        };

        OwnerCache::put($owner, 'shared', 'value');

        expect(fn () => OwnerCache::forgetOwner($owner))->not->toThrow(Throwable::class);
    });
});
