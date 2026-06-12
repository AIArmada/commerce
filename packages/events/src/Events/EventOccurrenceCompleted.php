<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventOccurrence;

final class EventOccurrenceCompleted
{
    public function __construct(
        public EventOccurrence $occurrence,
    ) {}
}
