<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventInvolvement;

final class EventSpeakerChanged
{
    public function __construct(
        public EventInvolvement $oldInvolvement,
        public EventInvolvement $newInvolvement,
    ) {}
}
