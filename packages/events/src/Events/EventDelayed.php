<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventOccurrence;
use DateTimeInterface;

final class EventDelayed
{
    public function __construct(
        public EventOccurrence $occurrence,
        public ?string $reason = null,
        public ?DateTimeInterface $expectedStartsAt = null,
    ) {}
}
