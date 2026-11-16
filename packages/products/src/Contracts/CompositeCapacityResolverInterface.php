<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;

/**
 * Resolves per-component capacity using arbitrary application context.
 *
 * For nested components the context includes composition_path, the ordered
 * catalog ancestry from the root package to the exact leaf component.
 * No booking or inventory allocations occur through this read contract.
 * Return null for unlimited capacity or a non-negative finite count.
 */
interface CompositeCapacityResolverInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function availableFor(Product $parent, ProductComponent $component, array $context = []): ?int;
}
