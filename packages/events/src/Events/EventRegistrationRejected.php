<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventRegistrationRejected
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $reason,
        public ?string $registrantType = null,
        public ?string $registrantId = null,
    ) {}
}
