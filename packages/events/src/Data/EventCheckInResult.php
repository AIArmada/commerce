<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventAttendance;

final readonly class EventCheckInResult
{
    public function __construct(
        public EventAttendance $attendance,
        public bool $created,
    ) {}
}
