<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Models\Event;

final class DefaultEventClassificationResolver implements EventClassificationResolver
{
    public function resolve(Event $event): array
    {
        return [];
    }
}
