<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

interface EventSearchDocumentBuilder
{
    public function buildForEvent(Event $event): array;

    public function buildForOccurrence(EventOccurrence $occurrence): array;

    public function buildForSession(EventSession $session): array;
}
