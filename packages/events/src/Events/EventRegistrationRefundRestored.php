<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventRegistrationRefundRestored
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public ?string $reason = null,
    ) {}
}
