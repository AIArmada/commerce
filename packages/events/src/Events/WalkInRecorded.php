<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class WalkInRecorded
{
    public function __construct(
        public string $walkInId,
        public string $eventId,
        public ?string $occurrenceId = null,
        public ?string $sessionId = null,
    ) {}
}
