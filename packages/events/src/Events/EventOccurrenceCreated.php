<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventOccurrenceCreated
{
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
    ) {}
}
