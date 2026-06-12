<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Event;

final class EventPublished
{
    public function __construct(
        public Event $event,
    ) {}
}
