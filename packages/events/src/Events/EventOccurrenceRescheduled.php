<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventOccurrence;

final class EventOccurrenceRescheduled
{
    public function __construct(
        public EventOccurrence $oldOccurrence,
        public EventOccurrence $newOccurrence,
    ) {}
}
