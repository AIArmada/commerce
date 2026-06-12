<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventPass;

interface EventSeatAllocator
{
    public function allocate(EventPass $pass, array $preferences = []): ?\AIArmada\Events\Models\EventSeatAllocation;
}
