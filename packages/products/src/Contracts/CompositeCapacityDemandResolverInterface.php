<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Support\CompositeCapacityDemand;

/**
 * Optional richer capacity seam for scoped, shared or weighted resources.
 *
 * Implementations are also CompositeCapacityResolverInterface instances, so
 * legacy unscoped stock resolvers remain valid. The demand method is used by
 * composite availability when supplied; no allocation or hold is performed.
 */
interface CompositeCapacityDemandResolverInterface extends CompositeCapacityResolverInterface
{
    /**
     * @param  array<string, mixed>  $context  Includes composition_path for nested leaves.
     */
    public function demandFor(Product $parent, ProductComponent $component, array $context = []): CompositeCapacityDemand;
}
