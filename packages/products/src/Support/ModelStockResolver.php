<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use Illuminate\Database\Eloquent\Model;

final class ModelStockResolver implements CompositeStockResolverInterface
{
    public function availableFor(Model $inventoryable): int
    {
        if (! method_exists($inventoryable, 'getStockQuantity')) {
            return 0;
        }

        return max(0, (int) $inventoryable->getStockQuantity());
    }
}
