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

        if (isset($event->cart) && $event->cart instanceof Cart) {
            return CartReservationIdentity::fromCart($event->cart);
        }

        return null;
    }
}
