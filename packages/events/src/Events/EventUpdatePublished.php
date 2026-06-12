<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventUpdate;

final class EventUpdatePublished
{
    public function __construct(
        public EventUpdate $update,
    ) {}
}
