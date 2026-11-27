<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use AIArmada\Products\Data\CompositeCapacityRequest;

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
    public function availableFor(CompositeCapacityRequest $request): ?int;
}
