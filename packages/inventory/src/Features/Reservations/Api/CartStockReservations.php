<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Api;

use AIArmada\Inventory\Features\Stock\Api\CommittedStockMovement;

interface CartStockReservations
{
    /** @return list<CommittedStockMovement> */
    public function commit(string $cartId, ?string $orderId = null): array;

    public function releaseAllForCart(string $cartId): int;
}
