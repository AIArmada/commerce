<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventPublished
{
    public function __construct(
        public string $eventId,
    ) {}
}
