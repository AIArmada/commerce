<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventCheckInResult;

interface EventAdministrativeCheckIn
{
    public function checkInPass(string $passId, mixed $actor): EventCheckInResult;

    public function checkInWalkIn(string $eventId, mixed $actor, ?string $name = null, ?string $email = null): EventCheckInResult;
}
