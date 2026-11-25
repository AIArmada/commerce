<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use Carbon\CarbonImmutable;

final readonly class EventSessionRescheduled
{
    public function __construct(
        public string $sessionId,
        public string $eventId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $occurrenceId = null,
        public ?CarbonImmutable $previousStartsAt = null,
        public ?CarbonImmutable $previousEndsAt = null,
        public ?string $reason = null,
    ) {}
}
