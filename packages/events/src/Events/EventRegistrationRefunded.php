<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventRegistration;

final class EventRegistrationRefunded
{
    public function __construct(
        public EventRegistration $registration,
        public ?string $reason = null,
    ) {}
}
