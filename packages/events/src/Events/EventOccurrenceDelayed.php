<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use Carbon\CarbonImmutable;

final readonly class EventOccurrenceDelayed
{
    public function __construct(
        public string $occurrenceId,
        public string $eventId,
        public ?string $reason = null,
        public ?CarbonImmutable $expectedStartsAt = null,
    ) {}
}
