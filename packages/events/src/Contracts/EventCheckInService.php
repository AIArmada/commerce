<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventCheckInResult;
use AIArmada\Events\Models\EventAttendance;

interface EventCheckInService
{
    public function checkIn(array $data): EventAttendance;

    public function checkInWithResult(array $data): EventCheckInResult;

    public function checkOut(EventAttendance $attendance, mixed $actor = null): void;

    public function cancelCheckIn(EventAttendance $attendance, string $reason, mixed $actor = null): void;
}
