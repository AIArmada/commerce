<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * CART-06 regression gate.
 *
 * Automatic empty-cart cleanup is part of the mutation's consistency
 * boundary: it may only run while the revision the emptying mutation
 * committed is still current. A newer write (from an event listener or a
 * concurrent request) must keep its state, a recreated row must never be
 * deleted, and no cleanup event may describe a cleanup that did not happen.
 */
describe('CART-06 guarded automatic empty-cart cleanup', function (): void {
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

    it('preserves an item added by a removal listener and skips the destroy', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('cleanup-listener-destroy', ['a' => ($this->item)('a', 100, 1)]);

        $other = new Cart($this->storage, 'cleanup-listener-destroy');

        $events = new Dispatcher;
        $destroyed = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });
        $events->listen(ItemRemoved::class, function () use ($other): void {
            $other->add('b', 'Item b', 200, 1);
        });

        $cart = new Cart($this->storage, 'cleanup-listener-destroy', $events);

        $cart->remove('a');

        $items = $this->storage->getItems('cleanup-listener-destroy', 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($items['b']['quantity'])->toBe(1)
            ->and($this->storage->has('cleanup-listener-destroy', 'default'))->toBeTrue()
            ->and($destroyed)->toBe(0);
    });

    it('preserves an item added between the commit and a destroy cleanup', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('cleanup-hook-destroy', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'cleanup-hook-destroy');

        $events = new Dispatcher;
        $destroyed = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });

        $cart = new Cart($interleaved, 'cleanup-hook-destroy', $events);

        // The competing add commits between the emptying commit and the
        // automatic cleanup, so the guarded cleanup must leave it alone.
        $interleaved->interleaveAfter('putItems', fn () => $other->add('b', 'Item b', 200, 1));

        $cart->remove('a');

        $items = $this->storage->getItems('cleanup-hook-destroy', 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($items['b']['quantity'])->toBe(1)
            ->and($this->storage->has('cleanup-hook-destroy', 'default'))->toBeTrue()
            ->and($destroyed)->toBe(0);
    });

    it('preserves an item added by a removal listener and skips the clear', function (): void {
        config()->set('cart.empty_cart_behavior', 'clear');
        ($this->seed)('cleanup-listener-clear', ['a' => ($this->item)('a', 100, 1)]);

        $other = new Cart($this->storage, 'cleanup-listener-clear');

        $events = new Dispatcher;
        $cleared = 0;
        $events->listen(CartCleared::class, function () use (&$cleared): void {
            $cleared++;
        });
        $events->listen(ItemRemoved::class, function () use ($other): void {
            $other->add('b', 'Item b', 200, 1);
            $other->setMetadata('note', 'from-listener');
        });

        $cart = new Cart($this->storage, 'cleanup-listener-clear', $events);

        $cart->remove('a');

        $items = $this->storage->getItems('cleanup-listener-clear', 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($this->storage->getAllMetadata('cleanup-listener-clear', 'default'))->toBe(['note' => 'from-listener'])
            ->and($this->storage->has('cleanup-listener-clear', 'default'))->toBeTrue()
            ->and($cleared)->toBe(0);
    });

    it('preserves an item added between the commit and a clear cleanup', function (): void {
        config()->set('cart.empty_cart_behavior', 'clear');
        ($this->seed)('cleanup-hook-clear', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'cleanup-hook-clear');

        $events = new Dispatcher;
        $cleared = 0;
        $events->listen(CartCleared::class, function () use (&$cleared): void {
            $cleared++;
        });

        $cart = new Cart($interleaved, 'cleanup-hook-clear', $events);

        $interleaved->interleaveAfter('putItems', fn () => $other->add('b', 'Item b', 200, 1));

        $cart->remove('a');

        $items = $this->storage->getItems('cleanup-hook-clear', 'default');

        expect(array_keys($items))->toBe(['b'])
            ->and($items['b']['quantity'])->toBe(1)
            ->and($this->storage->has('cleanup-hook-clear', 'default'))->toBeTrue()
            ->and($cleared)->toBe(0);
    });

    it('preserves metadata and conditions written between the commit and a destroy cleanup', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('cleanup-hook-metadata', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);

        $events = new Dispatcher;
        $destroyed = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });

        $cart = new Cart($interleaved, 'cleanup-hook-metadata', $events);

        $interleaved->interleaveAfter('putItems', function (): void {
            $this->storage->putMetadata('cleanup-hook-metadata', 'default', 'note', 'fresh');

            $snapshot = $this->storage->getSnapshot('cleanup-hook-metadata', 'default');
            assert($snapshot !== null, 'The cart row should exist between the commit and cleanup.');

            $this->storage->putConditions('cleanup-hook-metadata', 'default', [
                'deal' => [
                    'name' => 'deal',
                    'type' => 'discount',
                    'target' => 'cart@cart_subtotal/aggregate',
                    'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
                    'value' => '-5%',
                ],
            ], $snapshot->revision());
        });

        $cart->remove('a');

        expect($this->storage->getAllMetadata('cleanup-hook-metadata', 'default'))->toBe(['note' => 'fresh'])
            ->and($this->storage->getConditions('cleanup-hook-metadata', 'default'))->toHaveKey('deal')
            ->and($this->storage->has('cleanup-hook-metadata', 'default'))->toBeTrue()
            ->and($destroyed)->toBe(0);
    });

    it('does not delete a row recreated between the commit and a destroy cleanup', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('cleanup-hook-recreate', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'cleanup-hook-recreate');

        $events = new Dispatcher;
        $destroyed = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });

        $cart = new Cart($interleaved, 'cleanup-hook-recreate', $events);

        // The competing writer deletes the row and recreates it, so the
        // committed revision now points at a row UUID that no longer exists.
        $interleaved->interleaveAfter('putItems', function () use ($other): void {
            $other->destroy();
            $other->add('c', 'Item c', 300, 1);
        });

        $cart->remove('a');

        $items = $this->storage->getItems('cleanup-hook-recreate', 'default');

        expect(array_keys($items))->toBe(['c'])
            ->and($items['c']['quantity'])->toBe(1)
            ->and($this->storage->has('cleanup-hook-recreate', 'default'))->toBeTrue()
            ->and($destroyed)->toBe(0);
    });

    it('applies the destroy cleanup when the committed revision is still current', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('cleanup-applies-destroy', ['a' => ($this->item)('a', 100, 1)]);

        $events = new Dispatcher;
        $destroyed = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });

        $cart = new Cart($this->storage, 'cleanup-applies-destroy', $events);

        $cart->remove('a');

        expect($this->storage->has('cleanup-applies-destroy', 'default'))->toBeFalse()
            ->and($destroyed)->toBe(1);
    });

    it('applies the clear cleanup when the committed revision is still current', function (): void {
        config()->set('cart.empty_cart_behavior', 'clear');
        ($this->seed)('cleanup-applies-clear', ['a' => ($this->item)('a', 100, 1)]);
        $this->storage->putMetadata('cleanup-applies-clear', 'default', 'note', 'to-clear');

        $events = new Dispatcher;
        $cleared = 0;
        $events->listen(CartCleared::class, function () use (&$cleared): void {
            $cleared++;
        });

        $cart = new Cart($this->storage, 'cleanup-applies-clear', $events);

        $cart->remove('a');

        expect($this->storage->has('cleanup-applies-clear', 'default'))->toBeTrue()
            ->and($this->storage->getItems('cleanup-applies-clear', 'default'))->toBe([])
            ->and($this->storage->getAllMetadata('cleanup-applies-clear', 'default'))->toBe([])
            ->and($cleared)->toBe(1);
    });

    it('leaves the empty cart untouched with the preserve behavior', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('cleanup-preserve', ['a' => ($this->item)('a', 100, 1)]);
        $this->storage->putMetadata('cleanup-preserve', 'default', 'note', 'kept');

        $events = new Dispatcher;
        $destroyed = 0;
        $cleared = 0;
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });
        $events->listen(CartCleared::class, function () use (&$cleared): void {
            $cleared++;
        });

        $cart = new Cart($this->storage, 'cleanup-preserve', $events);

        $cart->remove('a');

        expect($this->storage->has('cleanup-preserve', 'default'))->toBeTrue()
            ->and($this->storage->getItems('cleanup-preserve', 'default'))->toBe([])
            ->and($this->storage->getAllMetadata('cleanup-preserve', 'default'))->toBe(['note' => 'kept'])
            ->and($destroyed)->toBe(0)
            ->and($cleared)->toBe(0);
    });
});
