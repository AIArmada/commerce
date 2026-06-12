<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Models\Event;

final class NullEventScheduleResolver implements EventScheduleResolver
{
    public function resolve(Event $event): array
    {
        return [];
    }
}
