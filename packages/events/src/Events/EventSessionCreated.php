<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSession;

final class EventSessionCreated
{
    public function __construct(
        public EventSession $session,
    ) {}
}
