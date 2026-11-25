<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventSessionDeleted
{
    public function __construct(
        public string $sessionId,
        public string $eventId,
    ) {}
}
