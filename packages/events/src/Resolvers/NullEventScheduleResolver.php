<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventScheduleResolver;

final class NullEventScheduleResolver implements EventScheduleResolver
{
    /**
     * @param  array<string, mixed>  $series
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>|null  $venue
     * @param  array<string, mixed>  $occurrence
     * @return array<string, mixed>|null
     */
    public function resolve(array $series, array $event, ?array $venue, array $occurrence): ?array
    {
        return null;
    }
}
