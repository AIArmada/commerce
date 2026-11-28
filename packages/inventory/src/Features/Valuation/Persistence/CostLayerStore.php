<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Persistence;

use AIArmada\Inventory\Features\Valuation\Models\InventoryCostLayer;
use InvalidArgumentException;

final readonly class CostLayerStore
{
    /** Consume a row already selected under the caller's write transaction and lock. */
    public function consumeLocked(InventoryCostLayer $layer, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Consumption quantity must be positive.');
        }

        $consumed = min($quantity, $layer->remaining_quantity);

        if ($consumed > 0) {
            $layer->update(['remaining_quantity' => $layer->remaining_quantity - $consumed]);
        }

        return $consumed;
    }
}
