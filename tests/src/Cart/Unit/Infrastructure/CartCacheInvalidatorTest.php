<?php

declare(strict_types=1);

use AIArmada\Cart\Infrastructure\Caching\CartCacheInvalidator;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

describe('CartCacheInvalidator', function (): void {
    beforeEach(function (): void {
        $this->arrayStore = new ArrayStore;
        $this->cache = new Repository($this->arrayStore);
        $this->invalidator = new CartCacheInvalidator($this->cache);
    });

    it('can be instantiated', function (): void {
        expect($this->invalidator)->toBeInstanceOf(CartCacheInvalidator::class);
    });

    it('invalidates cart cache', function (): void {
        // Pre-populate cache
        $this->cache->put('cart:user-123:default:items', 'cached-items', 3600);
        $this->cache->put('cart:user-123:default:conditions', 'cached-conditions', 3600);

        expect($this->cache->has('cart:user-123:default:items'))->toBeTrue();

        $this->invalidator->invalidateCart('user-123', 'default');

        expect($this->cache->has('cart:user-123:default:items'))->toBeFalse()
            ->and($this->cache->has('cart:user-123:default:conditions'))->toBeFalse();
    });

    it('invalidates cart cache with owner', function (): void {
        $this->cache->put('cart:user-456:default:Tenant:1:items', 'cached', 3600);

        expect($this->cache->has('cart:user-456:default:Tenant:1:items'))->toBeTrue();

        $this->invalidator->invalidateCart('user-456', 'default', 'Tenant', 1);

        expect($this->cache->has('cart:user-456:default:Tenant:1:items'))->toBeFalse();
    });

    it('invalidates specific keys', function (): void {
        $this->cache->put('cart:user-789:default:items', 'cached-items', 3600);
        $this->cache->put('cart:user-789:default:conditions', 'cached-conditions', 3600);
        $this->cache->put('cart:user-789:default:metadata', 'cached-metadata', 3600);

        $this->invalidator->invalidateKeys('user-789', 'default', ['items', 'conditions']);

        expect($this->cache->has('cart:user-789:default:items'))->toBeFalse()
            ->and($this->cache->has('cart:user-789:default:conditions'))->toBeFalse()
            ->and($this->cache->has('cart:user-789:default:metadata'))->toBeTrue();
    });

    it('invalidates identifier carts', function (): void {
        $this->cache->put('cart:user-id:default:items', 'cached', 3600);
        $this->cache->put('cart:user-id:wishlist:items', 'cached', 3600);

        $this->invalidator->invalidateIdentifier('user-id', ['default', 'wishlist']);

        expect($this->cache->has('cart:user-id:default:items'))->toBeFalse()
            ->and($this->cache->has('cart:user-id:wishlist:items'))->toBeFalse();
    });

    it('invalidates items cache', function (): void {
        $this->cache->put('cart:user:default:items', 'items', 3600);
        $this->cache->put('cart:user:default:version', 'v1', 3600);

        $this->invalidator->invalidateItems('user', 'default');

        expect($this->cache->has('cart:user:default:items'))->toBeFalse()
            ->and($this->cache->has('cart:user:default:version'))->toBeFalse();
    });

    it('invalidates conditions cache', function (): void {
        $this->cache->put('cart:user:default:conditions', 'conditions', 3600);
        $this->cache->put('cart:user:default:version', 'v1', 3600);

        $this->invalidator->invalidateConditions('user', 'default');

        expect($this->cache->has('cart:user:default:conditions'))->toBeFalse()
            ->and($this->cache->has('cart:user:default:version'))->toBeFalse();
    });

    it('invalidates metadata cache', function (): void {
        $this->cache->put('cart:user:default:metadata', 'metadata', 3600);
        $this->cache->put('cart:user:default:version', 'v1', 3600);

        $this->invalidator->invalidateMetadata('user', 'default');

        expect($this->cache->has('cart:user:default:metadata'))->toBeFalse()
            ->and($this->cache->has('cart:user:default:version'))->toBeFalse();
    });
});
