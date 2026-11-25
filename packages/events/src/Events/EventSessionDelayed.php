<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use Carbon\CarbonImmutable;

final readonly class EventSessionDelayed
{
    public function __construct(
        public string $sessionId,
        public string $eventId,
        public ?string $reason = null,
        public ?CarbonImmutable $expectedStartsAt = null,
    ) {}
}
