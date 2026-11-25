<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Api;

interface AttendanceApi
{
    public function hasAttendanceEvidence(string $registrationId): bool;

    public function registrationIdForAttendance(string $attendanceId): ?string;
}
