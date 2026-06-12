<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSession;

final class EventTopicChanged
{
    public function __construct(
        public Event | EventSession $target,
        public string $oldTopic,
        public string $newTopic,
    ) {}
}
