<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Data\AttendanceRecord;

final readonly class EventAttendanceCheckedIn
{
    public function __construct(
        public AttendanceRecord $attendance,
    ) {}
}
