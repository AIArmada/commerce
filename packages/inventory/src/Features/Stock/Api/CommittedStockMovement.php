<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

final readonly class CommittedStockMovement
{
    public function __construct(
        public string $id,
        public string $inventoryableType,
        public string $inventoryableId,
        public string $locationId,
        public int $quantity,
        public string $type,
        public ?string $reference,
        public ?string $batchId,
    ) {}
}
