<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Facades\Cart;
use Illuminate\Support\Facades\Event;

describe('CartDestroyed Event Dispatch', function (): void {
    beforeEach(function (): void {
        Event::fake(); // Fake events BEFORE any cart operations
        Cart::clear();
    });

    it('dispatches CartDestroyed event when destroying the cart', function (): void {
        // Add items first
        Cart::add('item-1', 'Item 1', 100.00, 1);
        Cart::add('item-2', 'Item 2', 50.00, 2);

        $identifier = Cart::getIdentifier();
        $instance = Cart::instance();

        // Destroy the cart
        Cart::destroy();

        // Assert CartDestroyed event was dispatched
        Event::assertDispatched(CartDestroyed::class, function (CartDestroyed $event) use ($identifier, $instance) {
            return $event->identifier === $identifier && $event->instance === $instance;
        });
    });

    it('dispatches CartDestroyed event even when cart is empty', function (): void {
        // Add item to create cart
        Cart::add('item-1', 'Item 1', 100.00, 1);

        // Clear it first
        Cart::clear();

        $identifier = Cart::getIdentifier();
        $instance = Cart::instance();

        // Destroy should still work and dispatch event
        Cart::destroy();

        Event::assertDispatched(CartDestroyed::class, function (CartDestroyed $event) use ($identifier, $instance) {
            return $event->identifier === $identifier && $event->instance === $instance;
        });
    });

    it('includes correct data in CartDestroyed event', function (): void {
        Cart::add('item-1', 'Item 1', 100.00, 1);

        $identifier = Cart::getIdentifier();
        $instance = Cart::instance();

        Cart::destroy();

        Event::assertDispatched(CartDestroyed::class, function (CartDestroyed $event) use ($identifier, $instance) {
            $data = $event->toArray();

            return $data['identifier'] === $identifier &&
                   $data['instance_name'] === $instance &&
                   isset($data['timestamp']);
        });
    });

    it('can destroy specific cart instance by identifier and instance name', function (): void {
        // Add to default instance
        Cart::add('item-1', 'Item 1', 100.00, 1);
        $identifier = Cart::getIdentifier();

        // Add to wishlist instance
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 50.00, 1);

        // Destroy only the default instance
        Cart::destroy($identifier, 'default');

        Event::assertDispatched(CartDestroyed::class, function (CartDestroyed $event) use ($identifier) {
            return $event->identifier === $identifier && $event->instance === 'default';
        });
    });

    it('dispatches CartDestroyed event when events are enabled', function (): void {
        Cart::add('item-1', 'Item 1', 100.00, 1);

        Cart::destroy();

        Event::assertDispatched(CartDestroyed::class);
    });
});
