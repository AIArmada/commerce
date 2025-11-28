<?php

declare(strict_types=1);

namespace AIArmada\Stock\Listeners;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Stock\Services\StockReservationService;

/**
 * Listener that releases stock reservations when a cart is cleared or destroyed.
 *
 * To queue this listener, extend it in your application and implement ShouldQueue.
 */
final class ReleaseStockOnCartClear
{
    public function __construct(
        private readonly StockReservationService $reservationService
    ) {}

    /**
     * Handle CartCleared event.
     */
    public function handleCleared(CartCleared $event): void
    {
        $cartId = $this->buildCartIdFromCart($event->cart);
        $this->reservationService->releaseAllForCart($cartId);
    }

    /**
     * Handle CartDestroyed event.
     */
    public function handleDestroyed(CartDestroyed $event): void
    {
        // CartDestroyed only has identifier and instance, not the cart object
        $cartId = sprintf('%s_%s', $event->identifier, $event->instance);
        $this->reservationService->releaseAllForCart($cartId);
    }

    /**
     * Build cart identifier from cart object.
     *
     * @param  mixed  $cart  Cart object with getId(), getIdentifier(), instance() methods
     */
    private function buildCartIdFromCart($cart): string
    {
        $cartId = method_exists($cart, 'getId') ? $cart->getId() : null;

        if ($cartId !== null) {
            return (string) $cartId;
        }

        $identifier = method_exists($cart, 'getIdentifier') ? $cart->getIdentifier() : 'default';
        $instance = method_exists($cart, 'instance') ? $cart->instance() : 'default';

        return sprintf('%s_%s', $identifier, $instance);
    }
}
