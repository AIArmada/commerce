<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Queries;

use AIArmada\Cart\Cart;

final readonly class CartReservationIdentity
{
    public static function fromCart(Cart $cart): string
    {
        $id = $cart->getId();

        return $id !== null && $id !== ''
            ? (string) $id
            : sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());
    }
}
