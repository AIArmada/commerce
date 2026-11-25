<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\AttendanceRecord;
use AIArmada\Events\Data\EventCheckInResult;

interface EventCheckInService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function checkIn(array $data): AttendanceRecord;

    /**
     * @param  array<string, mixed>  $data
     */
    public function checkInWithResult(array $data): EventCheckInResult;

    public function checkOut(string $attendanceId, mixed $actor = null): void;

    public function cancelCheckIn(string $attendanceId, string $reason, mixed $actor = null): void;
}
