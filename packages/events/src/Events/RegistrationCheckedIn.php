<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventAttendance;

final class RegistrationCheckedIn
{
    public function __construct(
        public EventAttendance $attendance,
    ) {}
}
