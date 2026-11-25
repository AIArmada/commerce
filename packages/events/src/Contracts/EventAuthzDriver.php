<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventAssignmentData;

interface EventAuthzDriver
{
    public function syncAssignment(EventAssignmentData $assignment): void;
}
