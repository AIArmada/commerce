<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Actions\CloneEventAction;
use AIArmada\Events\Actions\CloneEventOccurrenceAction;
use AIArmada\Events\Actions\CloneEventSessionAction;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

final class EventCloneServiceImpl implements EventCloneService
{
    public function __construct(
        private readonly CloneEventAction $cloneEvent,
        private readonly CloneEventOccurrenceAction $cloneOccurrence,
        private readonly CloneEventSessionAction $cloneSession,
    ) {}

    public function cloneEvent(Event $event, array $options = []): Event
    {
        return $this->cloneEvent->handle($event, $options);
    }

    public function cloneOccurrence(EventOccurrence $occurrence, array $options = []): EventOccurrence
    {
        return $this->cloneOccurrence->handle($occurrence, $options);
    }

    public function cloneSession(EventSession $session, array $options = []): EventSession
    {
        return $this->cloneSession->handle($session, $options);
    }
}
