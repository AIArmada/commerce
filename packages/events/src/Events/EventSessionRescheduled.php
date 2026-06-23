<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSession;

final class EventSessionRescheduled
{
    public function __construct(
        public EventSession $oldSession,
        public EventSession $newSession,
    ) {}
}
