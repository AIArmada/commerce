<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

interface EventCloneService
{
    public function cloneEvent(Event $event, array $options = []): Event;

    public function cloneOccurrence(EventOccurrence $occurrence, array $options = []): EventOccurrence;

    public function cloneSession(EventSession $session, array $options = []): EventSession;
}
