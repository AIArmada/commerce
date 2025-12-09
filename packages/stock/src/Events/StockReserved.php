<?php

declare(strict_types=1);

namespace AIArmada\Stock\Events;

use AIArmada\Stock\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when stock is reserved for a cart.
 */
final class StockReserved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $stockable,
        public readonly int $quantity,
        public readonly string $cartId,
        public readonly StockReservation $reservation
    ) {}
}
