<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventInvolvement;

final class EventInvolvementChanged
{
    public function __construct(
        public EventInvolvement $involvement,
        public string $changeType,
    ) {}
}
