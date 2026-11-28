<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

final readonly class ReorderStockLevel
{
    public function __construct(
        public string $id,
        public string $inventoryableType,
        public string $inventoryableId,
        public string $locationId,
        public int $available,
        public int $reserved,
        public ?int $safetyStock,
        public ?int $maxStock,
        public ?int $leadTimeDays,
    ) {}
}
