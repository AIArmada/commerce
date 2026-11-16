<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class ModelStockResolver implements CompositeStockResolverInterface
{
    public function availableFor(Model $inventoryable): ?int
    {
        // An untracked item has no warehouse limit. An application may bind a
        // different resolver to enforce another finite capacity constraint.
        if (method_exists($inventoryable, 'tracksInventory') && ! $inventoryable->tracksInventory()) {
            return null;
        }

        if (! method_exists($inventoryable, 'getStockQuantity')) {
            return 0;
        }

        return max(0, (int) $inventoryable->getStockQuantity());
    }
}
