<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Reservations\Queries\CartReservationIdentity;

final readonly class ReleaseInventoryOnCartClear
{
    public function __construct(
        private InventoryAllocationService $allocationService
    ) {}

    /**
     * Handle CartCleared event.
     */
    public function handleCleared(object $event): void
    {
        $this->releaseForCart($event);
    }

    /**
     * Handle CartDestroyed event.
     */
    public function handleDestroyed(object $event): void
    {
        $this->releaseForCart($event);
    }

    /**
     * Release allocations for a cart event.
     */
    private function releaseForCart(object $event): void
    {
        $cartId = $this->getCartIdentifier($event);

        if ($cartId !== null) {
            $this->allocationService->releaseAllForCart($cartId);
        }
    }

    /**
     * Extract cart identifier from event.
     */
    private function getCartIdentifier(object $event): ?string
    {
        foreach (['cartId', 'cart_id'] as $property) {
            if (isset($event->{$property}) && is_string($event->{$property}) && $event->{$property} !== '') {
                return $event->{$property};
            }
        }

        $cart = $event->cart ?? null;

        if ($cart instanceof Cart) {
            return CartReservationIdentity::fromCart($cart);
        }

        if (is_object($cart)) {
            $id = method_exists($cart, 'getId') ? $cart->getId() : null;

            if (is_string($id) && $id !== '') {
                return $id;
            }

            if (method_exists($cart, 'getIdentifier') && method_exists($cart, 'instance')) {
                return sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());
            }
        }

        return null;
    }
}
