<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventWalkIn;

final class WalkInRecorded
{
    public function __construct(
        public EventWalkIn $walkIn,
    ) {}
}
