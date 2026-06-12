<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventSeatAllocation;

interface EventSeatAllocator
{
    public function allocate(EventPass $pass, array $preferences = []): ?EventSeatAllocation;
}
