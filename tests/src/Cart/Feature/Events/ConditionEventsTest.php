<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\ItemConditionAdded;
use AIArmada\Cart\Events\ItemConditionRemoved;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Support\Facades\Event;

describe('Condition Added Events', function (): void {
    beforeEach(function (): void {
        Event::fake(); // Fake events BEFORE any cart operations
        Cart::clear();
    });

    it('dispatches CartConditionAdded event when adding cart-level condition', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        Event::assertDispatched(CartConditionAdded::class, function (CartConditionAdded $event) {
            return $event->condition->getName() === 'VAT' &&
                   $event->cart->total()->getAmount() === 110.00;
        });
    });

    it('includes comprehensive data in condition added event', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE20', '-20%');

        Event::assertDispatched(CartConditionAdded::class, function (CartConditionAdded $event) {
            return $event->condition->getName() === 'SAVE20' &&
                   $event->condition->getType() === 'discount' &&
                   isset($event->cart);
        });
    });

    it('calculates correct impact for condition added', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Cart::addTax('VAT', '10%');

        Event::assertDispatched(CartConditionAdded::class);
    });
});

describe('Condition Removed Events', function (): void {
    beforeEach(function (): void {
        Event::fake(); // Fake events BEFORE any cart operations
        Cart::clear();
    });

    it('dispatches CartConditionRemoved event when removing cart-level condition', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        Cart::removeCondition('VAT');

        Event::assertDispatched(CartConditionRemoved::class, function (CartConditionRemoved $event) {
            return $event->condition->getName() === 'VAT';
        });
    });

    it('calculates lost savings when removing discount', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE20', '-20%');

        Cart::removeCondition('SAVE20');

        Event::assertDispatched(CartConditionRemoved::class, function (CartConditionRemoved $event) {
            $impact = abs($event->getConditionImpact());

            return $impact === 20.00; // Lost savings for 20% discount
        });
    });

    it('shows zero lost savings for non-discount removals', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        Cart::removeCondition('VAT');

        Event::assertDispatched(CartConditionRemoved::class, function (CartConditionRemoved $event) {
            return $event->condition->getType() === 'tax'; // Removing tax, no lost savings
        });
    });

    it('does not dispatch event when removing non-existent condition', function (): void {
        Cart::add('item', 'Item', 100.00, 1);

        Event::fake();

        Cart::removeCondition('NonExistent');

        Event::assertNotDispatched(CartConditionRemoved::class);
    });
});

describe('Item Condition Events', function (): void {
    beforeEach(function (): void {
        Event::fake(); // Fake events BEFORE any cart operations
        Cart::clear();
    });

    it('dispatches events for item-level condition additions', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addItemCondition('item', new AIArmada\Cart\Conditions\CartCondition(
            name: 'Item Discount',
            type: 'discount',
            target: 'subtotal',
            value: '-10%'
        ));

        Event::assertDispatched(ItemConditionAdded::class, function (ItemConditionAdded $event) {
            return $event->condition->getName() === 'Item Discount';
        });
    });

    it('dispatches events for item-level condition removals', function (): void {
        Cart::add('item', 'Item', 100.00, 1);
        Cart::addItemCondition('item', new AIArmada\Cart\Conditions\CartCondition(
            name: 'Item Discount',
            type: 'discount',
            target: 'subtotal',
            value: '-10%'
        ));

        Cart::removeItemCondition('item', 'Item Discount');

        Event::assertDispatched(ItemConditionRemoved::class, function (ItemConditionRemoved $event) {
            return $event->condition->getName() === 'Item Discount';
        });
    });
});

describe('Condition Event Configuration', function (): void {
    it('does not dispatch events when disabled in config', function (): void {
        config(['cart.events' => false]); // Disable all cart events
        Event::fake(); // Fake FIRST
        Cart::clear();

        Cart::add('item', 'Item', 100.00, 1);
        Cart::addTax('VAT', '10%');

        Event::assertNotDispatched(CartConditionAdded::class);
    });

    it('works with helper methods for condition events', function (): void {
        Event::fake(); // Fake FIRST
        Cart::clear();

        Cart::add('item', 'Item', 100.00, 1);
        Cart::addDiscount('SAVE10', '-10%');
        Cart::addFee('Fee', '5.00'); // String value required
        Cart::addTax('Tax', '10%');

        Event::assertDispatched(CartConditionAdded::class, 3);
    });
});
