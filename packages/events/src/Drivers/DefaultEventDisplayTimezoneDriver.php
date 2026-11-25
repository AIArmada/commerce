<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;

final class DefaultEventDisplayTimezoneDriver implements EventDisplayTimezoneResolver
{
    public function resolve(?string $eventTimezone = null): string
    {
        return $eventTimezone ?? config('events.defaults.timezone', config('app.timezone', 'UTC'));
    }
}
