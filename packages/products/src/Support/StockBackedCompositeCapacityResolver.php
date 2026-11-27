<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Data\CompositeCapacityRequest;

final readonly class StockBackedCompositeCapacityResolver implements CompositeCapacityResolverInterface
{
    public function availableFor(CompositeCapacityRequest $request): ?int
    {
        return $request->stockAvailable;
    }
}
