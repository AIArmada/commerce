<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Carbon\CarbonImmutable;

final readonly class AttendanceRecord
{
    public function __construct(
        public string $id,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public ?string $registrationId,
        public ?string $participantId,
        public string $attendanceType,
        public ?string $checkInSource,
        public ?CarbonImmutable $checkedInAt = null,
        public ?CarbonImmutable $checkedOutAt = null,
        public ?CarbonImmutable $cancelledAt = null,
        public ?string $verifiedByUserId = null,
    ) {}
}
