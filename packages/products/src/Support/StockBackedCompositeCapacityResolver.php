<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;

/**
 * Default capacity policy: only warehouse-tracked components impose a limit.
 * Other kinds of finite capacity are delegated to application integrations.
 */
final class StockBackedCompositeCapacityResolver implements CompositeCapacityResolverInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function availableFor(Product $parent, ProductComponent $component, array $context = []): ?int
    {
        $inventoryable = $component->variant instanceof Variant
            ? $component->variant
            : $component->componentProduct;

        if (! $inventoryable instanceof Product && ! $inventoryable instanceof Variant) {
            throw new InvalidCompositePackageException('Composite component inventory model could not be resolved.');
        }

        if (! $inventoryable->tracksInventory()) {
            return null;
        }

        return max(0, $inventoryable->getStockQuantity());
    }
}
