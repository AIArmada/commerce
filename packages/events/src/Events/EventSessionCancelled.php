<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSession;

final class EventSessionCancelled
{
    public function __construct(
        public EventSession $session,
        public ?string $reason = null,
    ) {}
}
