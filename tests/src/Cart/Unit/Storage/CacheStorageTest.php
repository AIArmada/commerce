<?php

declare(strict_types=1);

use AIArmada\Cart\Storage\CacheStorage;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    $this->storage = new CacheStorage(Cache::store(), 'test_cart', 3600);
});

describe('CacheStorage', function (): void {
    it('stores and retrieves items', function (): void {
        $items = ['item-1' => ['name' => 'Test Item', 'price' => 10.00]];

        $this->storage->putItems('cart-123', 'default', $items);
        $retrieved = $this->storage->getItems('cart-123', 'default');

        expect($retrieved)->toBe($items);
    });

    it('stores and retrieves conditions', function (): void {
        $conditions = ['tax' => ['type' => 'percentage', 'value' => '10']];

        $this->storage->putConditions('cart-123', 'default', $conditions);
        $retrieved = $this->storage->getConditions('cart-123', 'default');

        expect($retrieved)->toBe($conditions);
    });

    it('stores both items and conditions at once', function (): void {
        $items = ['item-1' => ['name' => 'Item']];
        $conditions = ['tax' => ['value' => '10']];

        $this->storage->putBoth('cart-123', 'default', $items, $conditions);

        expect($this->storage->getItems('cart-123', 'default'))->toBe($items);
        expect($this->storage->getConditions('cart-123', 'default'))->toBe($conditions);
    });

    it('checks if cart exists in storage', function (): void {
        expect($this->storage->has('cart-123', 'default'))->toBeFalse();

        $this->storage->putItems('cart-123', 'default', ['item' => []]);

        expect($this->storage->has('cart-123', 'default'))->toBeTrue();
    });

    it('checks if cart exists with conditions only', function (): void {
        $this->storage->putConditions('cart-123', 'default', ['tax' => []]);

        expect($this->storage->has('cart-123', 'default'))->toBeTrue();
    });

    it('forgets specific cart instance', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);
        $this->storage->putItems('cart-123', 'wishlist', ['item' => []]);

        $this->storage->forget('cart-123', 'default');

        expect($this->storage->has('cart-123', 'default'))->toBeFalse();
        expect($this->storage->has('cart-123', 'wishlist'))->toBeTrue();
    });

    it('flushes all cart data with prefix', function (): void {
        $this->storage->putItems('cart-1', 'default', ['item' => []]);
        $this->storage->putItems('cart-2', 'default', ['item' => []]);

        $this->storage->flush();

        expect($this->storage->has('cart-1', 'default'))->toBeFalse();
        expect($this->storage->has('cart-2', 'default'))->toBeFalse();
    });

    it('returns stored instances for identifier', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);
        $this->storage->putItems('cart-123', 'wishlist', ['item' => []]);

        $instances = $this->storage->getInstances('cart-123');

        expect($instances)->toMatchArray(['default', 'wishlist']);
    });

    it('returns empty array for non-existent identifier instances', function (): void {
        $instances = $this->storage->getInstances('non-existent');

        expect($instances)->toBe([]);
    });

    it('forgetIdentifier clears all instances for identifier', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);
        $this->storage->putItems('cart-123', 'wishlist', ['item' => []]);
        $this->storage->putItems('cart-456', 'default', ['item' => []]);

        $this->storage->forgetIdentifier('cart-123');

        expect($this->storage->has('cart-123', 'default'))->toBeFalse();
        expect($this->storage->has('cart-123', 'wishlist'))->toBeFalse();
        expect($this->storage->has('cart-456', 'default'))->toBeTrue();
    });

    it('stores and retrieves metadata', function (): void {
        $this->storage->putMetadata('cart-123', 'default', 'notes', 'Customer notes here');
        $value = $this->storage->getMetadata('cart-123', 'default', 'notes');

        expect($value)->toBe('Customer notes here');
    });

    it('tracks versions, identifiers, and timestamps', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);

        $version = $this->storage->getVersion('cart-123', 'default');
        $id = $this->storage->getId('cart-123', 'default');
        $createdAt = $this->storage->getCreatedAt('cart-123', 'default');
        $updatedAt = $this->storage->getUpdatedAt('cart-123', 'default');

        expect($version)->toBeInt()->toBeGreaterThan(0);
        expect($id)->toBeString()->not->toBeEmpty();
        expect($createdAt)->toBeString();
        expect($updatedAt)->toBeString();

        $this->storage->putConditions('cart-123', 'default', ['tax' => ['value' => '10%']]);

        $newVersion = $this->storage->getVersion('cart-123', 'default');
        $newUpdatedAt = $this->storage->getUpdatedAt('cart-123', 'default');

        expect($newVersion)->toBeGreaterThan($version);
        expect($this->storage->getCreatedAt('cart-123', 'default'))->toBe($createdAt);
        expect(strtotime($newUpdatedAt))->toBeGreaterThanOrEqual(strtotime($updatedAt));
    });

    it('returns null for non-existent metadata', function (): void {
        $value = $this->storage->getMetadata('cart-123', 'default', 'non-existent');

        expect($value)->toBeNull();
    });

    it('returns empty array when getting items from non-existent cart', function (): void {
        $items = $this->storage->getItems('non-existent', 'default');

        expect($items)->toBe([]);
    });

    it('returns empty array when getting conditions from non-existent cart', function (): void {
        $conditions = $this->storage->getConditions('non-existent', 'default');

        expect($conditions)->toBe([]);
    });

    it('swaps identifier successfully', function (): void {
        $items = ['item-1' => ['name' => 'Product']];
        $conditions = ['tax' => ['value' => '10']];

        $this->storage->putBoth('old-cart', 'default', $items, $conditions);

        $result = $this->storage->swapIdentifier('old-cart', 'new-cart', 'default');

        expect($result)->toBeTrue();
        expect($this->storage->has('old-cart', 'default'))->toBeFalse();
        expect($this->storage->has('new-cart', 'default'))->toBeTrue();
        expect($this->storage->getItems('new-cart', 'default'))->toBe($items);
        expect($this->storage->getConditions('new-cart', 'default'))->toBe($conditions);
    });

    it('returns false when swapping non-existent identifier', function (): void {
        $result = $this->storage->swapIdentifier('non-existent', 'new-cart', 'default');

        expect($result)->toBeFalse();
    });

    it('respects TTL when storing items', function (): void {
        $storage = new CacheStorage(Cache::store(), 'test_cart', 1); // 1 second TTL

        $storage->putItems('cart-123', 'default', ['item' => []]);

        expect($storage->has('cart-123', 'default'))->toBeTrue();

        sleep(2); // Wait for TTL to expire

        expect($storage->has('cart-123', 'default'))->toBeFalse();
    });

    it('throws exception when items data size exceeds limit', function (): void {
        config()->set('cart.limits.max_data_size_bytes', 100);

        $largeItems = [];
        for ($i = 0; $i < 50; $i++) {
            $largeItems["item-{$i}"] = ['description' => str_repeat('x', 100)];
        }

        expect(fn () => $this->storage->putItems('cart-123', 'default', $largeItems))
            ->toThrow(InvalidArgumentException::class, 'data size');
    });

    it('throws exception when conditions data size exceeds limit', function (): void {
        config()->set('cart.limits.max_data_size_bytes', 100);

        $largeConditions = [];
        for ($i = 0; $i < 50; $i++) {
            $largeConditions["condition-{$i}"] = ['description' => str_repeat('x', 100)];
        }

        expect(fn () => $this->storage->putConditions('cart-123', 'default', $largeConditions))
            ->toThrow(InvalidArgumentException::class, 'data size');
    });

    it('handles multiple instances per identifier correctly', function (): void {
        // Create multiple instances for same identifier
        $this->storage->putItems('user-1', 'cart', ['item1' => []]);
        $this->storage->putItems('user-1', 'wishlist', ['item2' => []]);
        $this->storage->putItems('user-2', 'cart', ['item3' => []]);

        // Verify all exist independently
        expect($this->storage->has('user-1', 'cart'))->toBeTrue();
        expect($this->storage->has('user-1', 'wishlist'))->toBeTrue();
        expect($this->storage->has('user-2', 'cart'))->toBeTrue();

        // Forget one instance
        $this->storage->forget('user-1', 'cart');

        // Others should remain
        expect($this->storage->has('user-1', 'cart'))->toBeFalse();
        expect($this->storage->has('user-1', 'wishlist'))->toBeTrue();
        expect($this->storage->has('user-2', 'cart'))->toBeTrue();
    });

    it('stores metadata with correct TTL', function (): void {
        $storage = new CacheStorage(Cache::store(), 'test_cart', 1); // 1 second TTL

        $storage->putMetadata('cart-123', 'default', 'notes', 'Test notes');

        expect($storage->getMetadata('cart-123', 'default', 'notes'))->toBe('Test notes');

        sleep(2);

        expect($storage->getMetadata('cart-123', 'default', 'notes'))->toBeNull();
    });

    it('handles cache key generation correctly', function (): void {
        // Test that different identifiers and instances use different cache keys
        $this->storage->putItems('cart-1', 'default', ['a' => 1]);
        $this->storage->putItems('cart-1', 'wishlist', ['b' => 2]);
        $this->storage->putItems('cart-2', 'default', ['c' => 3]);

        expect($this->storage->getItems('cart-1', 'default'))->toBe(['a' => 1]);
        expect($this->storage->getItems('cart-1', 'wishlist'))->toBe(['b' => 2]);
        expect($this->storage->getItems('cart-2', 'default'))->toBe(['c' => 3]);
    });

    it('handles empty data gracefully', function (): void {
        $this->storage->putItems('cart-123', 'default', []);
        $this->storage->putConditions('cart-123', 'default', []);

        expect($this->storage->getItems('cart-123', 'default'))->toBe([]);
        expect($this->storage->getConditions('cart-123', 'default'))->toBe([]);
    });

    it('uses locking when enabled', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        $items = ['item-1' => ['name' => 'Product']];
        $storageWithLocking->putItems('cart-123', 'default', $items);

        expect($storageWithLocking->getItems('cart-123', 'default'))->toBe($items);
    });

    it('handles putBoth with locking enabled', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        $items = ['item-1' => ['name' => 'Product']];
        $conditions = ['tax' => ['value' => '10']];

        $storageWithLocking->putBoth('cart-123', 'default', $items, $conditions);

        expect($storageWithLocking->getItems('cart-123', 'default'))->toBe($items);
        expect($storageWithLocking->getConditions('cart-123', 'default'))->toBe($conditions);
    });

    it('handles metadata with locking enabled', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        $storageWithLocking->putMetadata('cart-123', 'default', 'notes', 'Test value');

        expect($storageWithLocking->getMetadata('cart-123', 'default', 'notes'))->toBe('Test value');
    });

    it('uses custom lock timeout', function (): void {
        $storage = new CacheStorage(Cache::store(), 'test_cart', 3600, true, 10);

        $items = ['item-1' => ['name' => 'Product']];
        $storage->putItems('cart-123', 'default', $items);

        expect($storage->getItems('cart-123', 'default'))->toBe($items);
    });

    it('handles concurrent writes with locking', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        // Simulate multiple writes
        $storageWithLocking->putItems('cart-123', 'default', ['item-1' => ['name' => 'First']]);
        $storageWithLocking->putItems('cart-123', 'default', ['item-2' => ['name' => 'Second']]);

        $items = $storageWithLocking->getItems('cart-123', 'default');

        expect($items)->toHaveKey('item-2');
    });

    it('stores metadata without locking', function (): void {
        // Disable locking
        $storage = new CacheStorage(Cache::store(), 'test_cart', 3600, false);

        $storage->putMetadata('cart-123', 'default', 'user_id', 456);

        expect($storage->getMetadata('cart-123', 'default', 'user_id'))->toBe(456);
    });

    it('throws exception when item count exceeds limit', function (): void {
        config()->set('cart.limits.max_items', 5);

        $items = [];
        for ($i = 1; $i <= 10; $i++) {
            $items["item-{$i}"] = ['name' => "Item {$i}"];
        }

        expect(fn () => $this->storage->putItems('cart-123', 'default', $items))
            ->toThrow(InvalidArgumentException::class, 'cannot contain more than');
    });

    it('stores metadata with locking enabled and retrieves it', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        $storageWithLocking->putMetadata('cart-123', 'default', 'session', 'abc123');
        $storageWithLocking->putMetadata('cart-123', 'default', 'user_id', 789);

        expect($storageWithLocking->getMetadata('cart-123', 'default', 'session'))->toBe('abc123');
        expect($storageWithLocking->getMetadata('cart-123', 'default', 'user_id'))->toBe(789);
    });

    it('stores conditions with locking', function (): void {
        $storageWithLocking = new CacheStorage(Cache::store(), 'test_cart', 3600, true);

        $conditions = ['discount' => ['type' => 'fixed', 'value' => 5]];
        $storageWithLocking->putConditions('cart-123', 'default', $conditions);

        expect($storageWithLocking->getConditions('cart-123', 'default'))->toEqual($conditions);
    });

    it('handles JSON string data when retrieving items', function (): void {
        $items = ['item-1' => ['name' => 'Test Item', 'price' => 10.00]];
        $jsonData = json_encode($items);

        // Mock cache to return JSON string (simulates Redis/Memcached behavior)
        $mockCache = Mockery::mock(Cache::store());
        $mockCache->shouldReceive('get')
            ->with('test_cart.cart-123.default.items')
            ->andReturn($jsonData);

        $storage = new CacheStorage($mockCache, 'test_cart', 3600);
        $retrieved = $storage->getItems('cart-123', 'default');

        expect($retrieved)->toEqual($items);
        expect($retrieved['item-1']['name'])->toBe('Test Item');
    });

    it('handles JSON string data when retrieving conditions', function (): void {
        $conditions = ['tax' => ['type' => 'percentage', 'value' => '10']];
        $jsonData = json_encode($conditions);

        // Mock cache to return JSON string (simulates Redis/Memcached behavior)
        $mockCache = Mockery::mock(Cache::store());
        $mockCache->shouldReceive('get')
            ->with('test_cart.cart-123.default.conditions')
            ->andReturn($jsonData);

        $storage = new CacheStorage($mockCache, 'test_cart', 3600);
        $retrieved = $storage->getConditions('cart-123', 'default');

        expect($retrieved)->toBe($conditions);
    });

    it('returns empty array when JSON string is invalid for items', function (): void {
        // Mock cache to return invalid JSON string
        $mockCache = Mockery::mock(Cache::store());
        $mockCache->shouldReceive('get')
            ->with('test_cart.cart-123.default.items')
            ->andReturn('invalid-json{');

        $storage = new CacheStorage($mockCache, 'test_cart', 3600);
        $retrieved = $storage->getItems('cart-123', 'default');

        expect($retrieved)->toBe([]);
    });

    it('returns empty array when JSON string is invalid for conditions', function (): void {
        // Mock cache to return invalid JSON string
        $mockCache = Mockery::mock(Cache::store());
        $mockCache->shouldReceive('get')
            ->with('test_cart.cart-123.default.conditions')
            ->andReturn('invalid-json{');

        $storage = new CacheStorage($mockCache, 'test_cart', 3600);
        $retrieved = $storage->getConditions('cart-123', 'default');

        expect($retrieved)->toBe([]);
    });
});
