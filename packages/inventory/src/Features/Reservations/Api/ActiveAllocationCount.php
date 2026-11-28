<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Api;

interface ActiveAllocationCount
{
    public function count(): int;
}
