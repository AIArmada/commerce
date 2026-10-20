<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * CART-05 regression gate.
 *
 * Result data produced by a losing mutation attempt must never leak into the
 * dispatch or return of the committed attempt: every retry recomputes against
 * fresh content, and only the attempt that committed may decide the event,
 * the return value, and empty-cart handling.
 */
describe('CART-05 attempt-local mutation results', function (): void {
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

    it('emits only the committed update when a losing removal attempt is recomputed', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-remove-to-update', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-remove-to-update');

        $events = new Dispatcher;
        $removed = 0;
        $updatedQuantities = [];
        $events->listen(ItemRemoved::class, function () use (&$removed): void {
            $removed++;
        });
        $events->listen(ItemUpdated::class, function (ItemUpdated $event) use (&$updatedQuantities): void {
            $updatedQuantities[] = $event->item->quantity;
        });

        $cart = new Cart($interleaved, 'retry-remove-to-update', $events);

        // The competing writer raises the quantity from 1 to 6 between the
        // snapshot read and the write, so the first (removal) attempt loses
        // and the recomputed attempt is a quantity update to 5.
        $interleaved->interleaveAfter('getSnapshot', fn () => $other->add('a', 'Item a', 100, 5));

        $returned = $cart->update('a', ['quantity' => -1]);

        $items = $this->storage->getItems('retry-remove-to-update', 'default');

        expect($returned)->not->toBeNull()
            ->and($returned->id)->toBe('a')
            ->and($returned->quantity)->toBe(5)
            ->and($items['a']['quantity'])->toBe(5)
            ->and($updatedQuantities)->toBe([5])
            ->and($removed)->toBe(0);
    });

    it('emits only the committed removal when a losing update attempt is recomputed as a removal', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-update-to-remove', ['a' => ($this->item)('a', 100, 2)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-update-to-remove');

        $events = new Dispatcher;
        $removed = 0;
        $updated = 0;
        $events->listen(ItemRemoved::class, function () use (&$removed): void {
            $removed++;
        });
        $events->listen(ItemUpdated::class, function () use (&$updated): void {
            $updated++;
        });

        $cart = new Cart($interleaved, 'retry-update-to-remove', $events);

        // An absolute quantity write lowers 2 -> 1 between the read and the
        // write, so the first (update) attempt loses and the recomputed
        // attempt removes the item.
        $interleaved->interleaveAfter('getSnapshot', fn () => $other->update('a', ['quantity' => ['value' => 1]]));

        $returned = $cart->update('a', ['quantity' => -1]);

        expect($returned)->not->toBeNull()
            ->and($returned->id)->toBe('a')
            ->and($returned->quantity)->toBe(1)
            ->and($this->storage->getItems('retry-update-to-remove', 'default'))->toBe([])
            ->and($removed)->toBe(1)
            ->and($updated)->toBe(0);
    });

    it('returns null and dispatches nothing when a losing remover finds the item already gone', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-remove-loser', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-remove-loser');

        $events = new Dispatcher;
        $removed = 0;
        $events->listen(ItemRemoved::class, function () use (&$removed): void {
            $removed++;
        });

        $cart = new Cart($interleaved, 'retry-remove-loser', $events);

        $interleaved->interleaveAfter('getSnapshot', fn () => $other->remove('a'));

        $returned = $cart->remove('a');

        expect($returned)->toBeNull()
            ->and($removed)->toBe(0)
            ->and($this->storage->has('retry-remove-loser', 'default'))->toBeTrue()
            ->and($this->storage->getItems('retry-remove-loser', 'default'))->toBe([]);
    });

    it('returns null and dispatches nothing when a losing update finds the item already gone', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-update-loser', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-update-loser');

        $events = new Dispatcher;
        $mutations = 0;
        $events->listen(ItemRemoved::class, function () use (&$mutations): void {
            $mutations++;
        });
        $events->listen(ItemUpdated::class, function () use (&$mutations): void {
            $mutations++;
        });

        $cart = new Cart($interleaved, 'retry-update-loser', $events);

        $interleaved->interleaveAfter('getSnapshot', fn () => $other->remove('a'));

        $returned = $cart->update('a', ['quantity' => -1]);

        expect($returned)->toBeNull()
            ->and($mutations)->toBe(0)
            ->and($this->storage->has('retry-update-loser', 'default'))->toBeTrue()
            ->and($this->storage->getItems('retry-update-loser', 'default'))->toBe([]);
    });

    it('does not run empty-cart cleanup for a non-committing removal attempt', function (): void {
        config()->set('cart.empty_cart_behavior', 'destroy');
        ($this->seed)('retry-no-cleanup', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-no-cleanup');

        $events = new Dispatcher;
        $removed = 0;
        $destroyed = 0;
        $events->listen(ItemRemoved::class, function () use (&$removed): void {
            $removed++;
        });
        $events->listen(CartDestroyed::class, function () use (&$destroyed): void {
            $destroyed++;
        });

        $cart = new Cart($interleaved, 'retry-no-cleanup', $events);

        // The competing writer removes the last item first, so the retry finds
        // nothing to mutate and must return null without dispatching the
        // losing attempt's removal or running empty-cart cleanup.
        $interleaved->interleaveAfter('getSnapshot', fn () => $other->remove('a'));

        expect($cart->remove('a'))->toBeNull()
            ->and($removed)->toBe(0)
            ->and($destroyed)->toBe(0)
            ->and($this->storage->has('retry-no-cleanup', 'default'))->toBeFalse();
    });

    it('surfaces an exhausted conflict without dispatching the losing attempts', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-exhausted', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-exhausted');

        $events = new Dispatcher;
        $removed = 0;
        $events->listen(ItemRemoved::class, function () use (&$removed): void {
            $removed++;
        });

        $cart = new Cart($interleaved, 'retry-exhausted', $events);

        // Re-arm before each competing write so every bounded retry loses the
        // race and the conflict is surfaced to the caller.
        $arm = null;
        $arm = function () use (&$arm, $interleaved, $other): void {
            $interleaved->interleaveAfter('getSnapshot', $arm);
            $other->add('b', 'Item b', 200, 1);
        };
        $interleaved->interleaveAfter('getSnapshot', $arm);

        expect(fn () => $cart->remove('a'))->toThrow(CartConflictException::class);

        $items = $this->storage->getItems('retry-exhausted', 'default');

        expect($removed)->toBe(0)
            ->and($items)->toHaveKey('a')
            ->and($items['a']['quantity'])->toBe(1)
            ->and($items['b']['quantity'])->toBe(3);
    });

    it('does not dispatch a condition removal when the retry finds it already gone', function (): void {
        config()->set('cart.empty_cart_behavior', 'preserve');
        ($this->seed)('retry-condition', ['a' => ($this->item)('a', 100, 1)]);

        $interleaved = new InterleavingStorage($this->storage);
        $other = new Cart($this->storage, 'retry-condition');
        $other->addCondition(new CartCondition('deal', 'discount', 'cart@cart_subtotal/aggregate', '-5%'));

        $events = new Dispatcher;
        $conditionRemoved = 0;
        $events->listen(CartConditionRemoved::class, function () use (&$conditionRemoved): void {
            $conditionRemoved++;
        });

        $cart = new Cart($interleaved, 'retry-condition', $events);

        $interleaved->interleaveAfter('getSnapshot', fn () => $other->removeCondition('deal'));

        expect($cart->removeCondition('deal'))->toBeFalse()
            ->and($conditionRemoved)->toBe(0);
    });
});
