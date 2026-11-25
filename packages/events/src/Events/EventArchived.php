<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventArchived
{
    public function __construct(
        public string $eventId,
        public ?string $occurrenceId = null,
        public ?string $reason = null,
    ) {}
}
