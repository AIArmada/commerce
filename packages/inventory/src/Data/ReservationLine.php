<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

final readonly class ReservationLine
{
    public function __construct(
        public string $productId,
        public ?string $variantId = null,
        public int $quantity = 1,
        public ?string $inventoryableType = null,
        public ?string $inventoryableId = null,
    ) {}
}
