<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventScheduleResolver;

final class NullEventScheduleDriver implements EventScheduleResolver
{
    public function resolve(string $eventId): iterable
    {
        return [];
    }
}
