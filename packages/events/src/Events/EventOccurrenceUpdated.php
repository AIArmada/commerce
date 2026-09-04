<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventOccurrence;

final class EventOccurrenceUpdated
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public EventOccurrence $occurrence,
        public array $changes = [],
    ) {}
}
