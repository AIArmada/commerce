<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use AIArmada\Products\Data\CompositeCapacityDemand;
use AIArmada\Products\Data\CompositeCapacityRequest;

/**
 * Resolves scoped, shared or weighted capacity without allocating resources.
 */
interface CompositeCapacityDemandResolverInterface extends CompositeCapacityResolverInterface
{
    public function demandFor(CompositeCapacityRequest $request): CompositeCapacityDemand;
}
