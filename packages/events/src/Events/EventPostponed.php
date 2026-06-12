<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

final class EventPostponed
{
    public function __construct(
        public Event | EventOccurrence $target,
        public ?string $reason = null,
    ) {}
}
