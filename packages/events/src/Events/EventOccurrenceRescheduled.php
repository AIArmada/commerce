<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use Carbon\CarbonImmutable;

final readonly class EventOccurrenceRescheduled
{
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?CarbonImmutable $previousStartsAt = null,
        public ?CarbonImmutable $previousEndsAt = null,
        public ?string $reason = null,
    ) {}
}
