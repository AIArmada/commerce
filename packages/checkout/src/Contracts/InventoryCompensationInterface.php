<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Contracts;

use AIArmada\Inventory\Data\ReservationOutcome;

interface InventoryCompensationInterface
{
    public function release(string $reference): ReservationOutcome;
}
