<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventLocation;

final class EventLocationChanged
{
    public function __construct(
        public EventLocation $oldLocation,
        public EventLocation $newLocation,
    ) {}
}
