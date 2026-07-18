<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

final class EventAudienceObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventAudience $audience): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        foreach ($this->resolveTargets($audience) as $target) {
            $this->indexer->index($target);
        }
    }

    public function deleted(EventAudience $audience): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        foreach ($this->resolveTargets($audience) as $target) {
            $this->indexer->index($target);
        }
    }

    /**
     * @return array<int, Event | EventOccurrence | EventSession>
     */
    private function resolveTargets(EventAudience $audience): array
    {
        $targets = [];

        $event = Event::find($audience->event_id);

        if ($event === null) {
            return [];
        }

        $targets[] = $event;

        if ($audience->event_session_id !== null) {
            $session = EventSession::find($audience->event_session_id);

            if ($session !== null) {
                $targets[] = $session;
            }

            return $targets;
        }

        if ($audience->event_occurrence_id !== null) {
            $occurrence = EventOccurrence::find($audience->event_occurrence_id);

            if ($occurrence !== null) {
                $targets[] = $occurrence;
            }
        }

        return $targets;
    }
}
