<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSession;
use DateTimeInterface;

final class EventSessionDelayed
{
    public function __construct(
        public EventSession $session,
        public ?string $reason = null,
        public ?DateTimeInterface $expectedStartsAt = null,
    ) {}
}
