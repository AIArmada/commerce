<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventScheduleValidationResult;

interface EventScheduleValidator
{
    public function validateSchedule(mixed $target): EventScheduleValidationResult;
}
