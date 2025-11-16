<?php

declare(strict_types=1);

use AIArmada\Cart\Storage\SessionStorage;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

beforeEach(function (): void {
    $this->session = new Store('testing', new ArraySessionHandler(120));
    $this->storage = new SessionStorage($this->session, 'test_cart');
});

describe('SessionStorage', function (): void {
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

    it('checks if cart exists with only items', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);

        expect($this->storage->has('cart-123', 'default'))->toBeTrue();
    });

    it('checks if cart exists with only conditions', function (): void {
        $this->storage->putConditions('cart-123', 'default', ['tax' => []]);

        expect($this->storage->has('cart-123', 'default'))->toBeTrue();
    });

    it('checks if cart does not exist', function (): void {
        expect($this->storage->has('cart-123', 'default'))->toBeFalse();
    });

    it('forgets specific cart instance', function (): void {
        $this->storage->putItems('cart-123', 'default', ['item' => []]);
        $this->storage->putConditions('cart-123', 'default', ['tax' => []]);
        $this->storage->putMetadata('cart-123', 'default', 'notes', 'test');

        $this->storage->forget('cart-123', 'default');

        expect($this->storage->has('cart-123', 'default'))->toBeFalse();
        expect($this->storage->getItems('cart-123', 'default'))->toBe([]);
        expect($this->storage->getConditions('cart-123', 'default'))->toBe([]);
        expect($this->storage->getMetadata('cart-123', 'default', 'notes'))->toBeNull();
    });

    it('flushes all cart data', function (): void {
        $this->storage->putItems('cart-1', 'default', ['item' => []]);
        $this->storage->putItems('cart-2', 'default', ['item' => []]);

        $this->storage->flush();

        expect($this->storage->has('cart-1', 'default'))->toBeFalse();
        expect($this->storage->has('cart-2', 'default'))->toBeFalse();
    });

    it('returns empty array for non-existent identifier instances', function (): void {
        $instances = $this->storage->getInstances('non-existent');

        expect($instances)->toBe([]);
    });

    it('stores and retrieves metadata', function (): void {
        $this->storage->putMetadata('cart-123', 'default', 'notes', 'Customer notes');

        $value = $this->storage->getMetadata('cart-123', 'default', 'notes');

        expect($value)->toBe('Customer notes');
    });

    it('returns null for non-existent metadata', function (): void {
        $value = $this->storage->getMetadata('cart-123', 'default', 'non-existent');

        expect($value)->toBeNull();
    });

    it('clears all metadata for instance', function (): void {
        $this->storage->putMetadata('cart-123', 'default', 'notes', 'test');
        $this->storage->putMetadata('cart-123', 'default', 'user_id', 123);

        $this->storage->clearMetadata('cart-123', 'default');

        expect($this->storage->getMetadata('cart-123', 'default', 'notes'))->toBeNull();
        expect($this->storage->getMetadata('cart-123', 'default', 'user_id'))->toBeNull();
    });

    it('returns null for version', function (): void {
        $version = $this->storage->getVersion('cart-123', 'default');

        expect($version)->toBeNull();
    });

    it('returns null for id', function (): void {
        $id = $this->storage->getId('cart-123', 'default');

        expect($id)->toBeNull();
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

    it('stores both items and conditions', function (): void {
        $items = ['item-1' => ['name' => 'Product']];
        $conditions = ['tax' => ['value' => '10']];

        $this->storage->putBoth('cart-123', 'default', $items, $conditions);

        expect($this->storage->getItems('cart-123', 'default'))->toBe($items);
        expect($this->storage->getConditions('cart-123', 'default'))->toBe($conditions);
    });

    it('returns null for created at and updated at', function (): void {
        $createdAt = $this->storage->getCreatedAt('cart-123', 'default');
        $updatedAt = $this->storage->getUpdatedAt('cart-123', 'default');

        expect($createdAt)->toBeNull();
        expect($updatedAt)->toBeNull();
    });
});
