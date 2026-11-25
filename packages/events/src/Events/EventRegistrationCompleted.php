<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventRegistrationCompleted
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
    ) {}
}
