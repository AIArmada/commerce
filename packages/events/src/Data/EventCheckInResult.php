<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventCheckInResult
{
    public function __construct(
        public AttendanceRecord $attendance,
        public bool $created,
    ) {}
}
