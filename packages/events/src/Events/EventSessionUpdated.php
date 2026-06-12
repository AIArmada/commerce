<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSession;

final class EventSessionUpdated
{
    public function __construct(
        public EventSession $session,
        public array $changes = [],
    ) {}
}
