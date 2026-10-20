<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * CART-02 regression gate.
 *
 * A cart read/modify/write cycle must never silently drop a concurrent
 * successful mutation. Each test deterministically interleaves a competing
 * real cart mutation between the read and the write of the cart under test.
 */
describe('CART-02 cart read/modify/write protection', function (): void {
    beforeEach(function (): void {
        DB::table('carts')->delete();

        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();
        $this->storage = new DatabaseStorage($connection, 'carts');
        $this->seed = function (string $identifier, array $items): void {
            $this->storage->putItems($identifier, 'default', $items, CartRevision::none());
        };
        $this->item = function (string $id, int $price, int $quantity = 1): array {
            return [
                'id' => $id,
                'name' => 'Item ' . $id,
                'price' => $price,
                'quantity' => $quantity,
                'attributes' => [],
                'conditions' => [],
            ];
        };
    });

    it('keeps both concurrent increments on the same item', function (): void {
        ($this->seed)('rw-same-item', ['seed' => ($this->item)('seed', 500, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-same-item');
        $cartB = new Cart($this->storage, 'rw-same-item');

        $interleaved->interleaveAfter('getSnapshot', function () use ($cartB): void {
            $cartB->add('seed', 'Item seed', 500, 1);
        });

        $cartA->add('seed', 'Item seed', 500, 1);

        $items = $this->storage->getItems('rw-same-item', 'default');

        expect($items)->toHaveKey('seed');
        expect($items['seed']['quantity'])->toBe(3);
    });

    it('retains both distinct items added concurrently to an existing cart', function (): void {
        ($this->seed)('rw-distinct', ['seed' => ($this->item)('seed', 500, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-distinct');
        $cartB = new Cart($this->storage, 'rw-distinct');

        $interleaved->interleaveAfter('getSnapshot', function () use ($cartB): void {
            $cartB->add('b', 'Item b', 200, 1);
        });

        $cartA->add('a', 'Item a', 100, 1);

        $items = $this->storage->getItems('rw-distinct', 'default');

        expect(array_keys($items))->toEqualCanonicalizing(['seed', 'a', 'b']);
    });

    it('retains both items when two carts are created concurrently', function (): void {
        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-first-create');
        $cartB = new Cart($this->storage, 'rw-first-create');

        $interleaved->interleaveAfter('getSnapshot', function () use ($cartB): void {
            $cartB->add('b', 'Item b', 200, 1);
        });

        $cartA->add('a', 'Item a', 100, 1);

        $items = $this->storage->getItems('rw-first-create', 'default');

        expect(array_keys($items))->toEqualCanonicalizing(['a', 'b']);
    });

    it('does not let a stale cart overwrite a recreated row', function (): void {
        ($this->seed)('rw-recreated', ['seed' => ($this->item)('seed', 500, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-recreated');
        $cartB = new Cart($this->storage, 'rw-recreated');

        $interleaved->interleaveAfter('getSnapshot', function () use ($cartB): void {
            $cartB->destroy();
            $cartB->add('b', 'Item b', 200, 1);
        });

        $cartA->add('a', 'Item a', 100, 1);

        $items = $this->storage->getItems('rw-recreated', 'default');

        expect(array_keys($items))->toEqualCanonicalizing(['a', 'b']);
    });

    it('keeps dynamic condition metadata registered concurrently by both carts', function (): void {
        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-metadata');
        $cartB = new Cart($this->storage, 'rw-metadata');

        $interleaved->interleaveAfter('mutateMetadata', function () use ($cartB): void {
            $cartB->registerDynamicCondition(
                new CartCondition(
                    name: 'b_discount',
                    type: 'discount',
                    target: 'cart@cart_subtotal/aggregate',
                    value: '-5%',
                    rules: [fn ($cart) => true],
                ),
                null,
                'min_order_discount',
            );
        });

        $cartA->registerDynamicCondition(
            new CartCondition(
                name: 'a_discount',
                type: 'discount',
                target: 'cart@cart_subtotal/aggregate',
                value: '-10%',
                rules: [fn ($cart) => true],
            ),
            null,
            'min_order_discount',
        );

        $metadata = $cartA->getDynamicConditionMetadata();

        expect($metadata)->toHaveKey('a_discount');
        expect($metadata)->toHaveKey('b_discount');
    });

    it('dispatches item added events once per committed mutation', function (): void {
        ($this->seed)('rw-events', ['seed' => ($this->item)('seed', 500, 1)]);

        $dispatched = [];
        $events = new Dispatcher;
        $events->listen('*', function (string $eventName, array $payload) use (&$dispatched): void {
            $dispatched[] = $payload[0] ?? null;
        });

        $interleaved = new InterleavingStorage($this->storage);
        $cartA = new Cart($interleaved, 'rw-events', $events);
        $cartB = new Cart($this->storage, 'rw-events');

        $interleaved->interleaveAfter('getSnapshot', function () use ($cartB): void {
            $cartB->add('b', 'Item b', 200, 1);
        });

        $cartA->add('a', 'Item a', 100, 1);

        $itemAdded = array_filter($dispatched, fn ($event) => $event instanceof ItemAdded);

        expect($itemAdded)->toHaveCount(1);

        $items = $this->storage->getItems('rw-events', 'default');

        expect(array_keys($items))->toEqualCanonicalizing(['seed', 'a', 'b']);
    });
});
