<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventDisplayTimezoneResolver
{
    public function resolve(?string $eventTimezone = null): string;
}
