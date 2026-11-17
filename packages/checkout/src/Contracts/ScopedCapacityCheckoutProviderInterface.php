<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Inventory\Data\ScopedCapacityLine;

/**
 * Optional application seam: derive pool demands from a frozen cart selection.
 * This contract does not model appointments, media or booking windows.
 */
interface ScopedCapacityCheckoutProviderInterface
{
    /** @return list<ScopedCapacityLine> */
    public function linesFor(CheckoutSession $session): array;
}
