<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;

final class EventClassificationObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventClassification $classification): void
    {
        if (
            ! config('events.sync.build_search_documents')
            || ! config('events.sync.classifications_to_facets')
            || $this->indexer === null
        ) {
            return;
        }

        $targets = $this->resolveTargets($classification);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->indexer->index($target);
        }
    }

    public function deleted(EventClassification $classification): void
    {
        if (
            ! config('events.sync.build_search_documents')
            || ! config('events.sync.classifications_to_facets')
            || $this->indexer === null
        ) {
            return;
        }

        $targets = $this->resolveTargets($classification);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->indexer->index($target);
        }
    }

    /**
     * @return array<int, Event | EventOccurrence | EventSession>
     */
    private function resolveTargets(EventClassification $classification): array
    {
        $targets = [];

        $event = Event::find($classification->event_id);

        if ($event === null) {
            return [];
        }

        $targets[] = $event;

        if ($classification->event_session_id !== null) {
            $session = EventSession::find($classification->event_session_id);

            if ($session !== null) {
                $targets[] = $session;
            }

            return $targets;
        }

        if ($classification->event_occurrence_id !== null) {
            $occurrence = EventOccurrence::find($classification->event_occurrence_id);

            if ($occurrence !== null) {
                $targets[] = $occurrence;
            }
        }

        return $targets;
    }
}
