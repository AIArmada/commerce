<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventOccurrenceUpdated
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
        public array $changes = [],
    ) {}
}
