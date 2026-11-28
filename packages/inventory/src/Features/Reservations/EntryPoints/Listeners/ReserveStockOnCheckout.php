<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Features\Reservations\Actions\ReserveCartInventory;

final readonly class ReserveStockOnCheckout
{
    public function __construct(private ReserveCartInventory $reserveCartInventory) {}

    public function handle(object $event): void
    {
        if (! config('inventory.cart.reserve_on_checkout', true)) {
            return;
        }

        if (property_exists($event, 'cart') && $event->cart instanceof Cart) {
            $this->reserveCartInventory->execute($event->cart);
        }
    }
}
