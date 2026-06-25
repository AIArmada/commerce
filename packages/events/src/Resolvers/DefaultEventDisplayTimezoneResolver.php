<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;

final class DefaultEventDisplayTimezoneResolver implements EventDisplayTimezoneResolver
{
    public function resolve(?string $eventTimezone = null): string
    {
        return $eventTimezone ?? config('events.defaults.timezone', config('app.timezone', 'UTC'));
    }
}
