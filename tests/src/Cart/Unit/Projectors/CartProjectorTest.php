<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Projectors\CartProjector;
use AIArmada\Cart\ReadModels\CartReadModel;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher;

describe('CartProjector', function (): void {
    beforeEach(function (): void {
        $this->connection = Mockery::mock(ConnectionInterface::class);
        $this->cache = new Repository(new ArrayStore);
        $this->storage = Mockery::mock(StorageInterface::class);
        $this->storage->shouldReceive('getOwnerType')->andReturn(null);
        $this->storage->shouldReceive('getOwnerId')->andReturn(null);

        $this->readModel = new CartReadModel($this->connection, $this->cache, $this->storage);
        $this->projector = new CartProjector($this->readModel);
    });

    it('can be instantiated', function (): void {
        expect($this->projector)->toBeInstanceOf(CartProjector::class);
    });

    it('subscribes to events', function (): void {
        $dispatcher = new Dispatcher;

        $this->projector->subscribe($dispatcher);

        expect($dispatcher->hasListeners(CartCreated::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartDestroyed::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartCleared::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemAdded::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemRemoved::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemUpdated::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartConditionAdded::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartConditionRemoved::class))->toBeTrue();
    });

    // Note: Handler tests would need proper event mocks with getCartId()
    // These are intentionally lightweight to avoid mocking final classes
});
