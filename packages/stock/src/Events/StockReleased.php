<?php

declare(strict_types=1);

namespace AIArmada\Stock\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when reserved stock is released.
 */
final class StockReleased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $stockable,
        public readonly int $quantity,
        public readonly string $cartId
    ) {}
}
