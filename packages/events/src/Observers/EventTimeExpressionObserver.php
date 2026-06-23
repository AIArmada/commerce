<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Services\EventMetadataSyncService;

final class EventTimeExpressionObserver
{
    public function __construct(
        private readonly EventMetadataSyncService $sync,
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventTimeExpression $expression): void
    {
        if (! config('events.sync.time_expressions_to_metadata')) {
            return;
        }

        $targets = $this->resolveTargets($expression);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->sync->syncTimeExpression($target);
        }

        if (config('events.sync.build_search_documents') && $this->indexer !== null) {
            foreach ($targets as $target) {
                $this->indexer->index($target);
            }
        }
    }

    public function deleted(EventTimeExpression $expression): void
    {
        if (! config('events.sync.time_expressions_to_metadata')) {
            return;
        }

        $targets = $this->resolveTargets($expression);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->sync->syncTimeExpression($target);
        }

        if (config('events.sync.build_search_documents') && $this->indexer !== null) {
            foreach ($targets as $target) {
                $this->indexer->index($target);
            }
        }
    }

    /**
     * @return array<int, Event | EventOccurrence | EventSession>
     */
    private function resolveTargets(EventTimeExpression $expression): array
    {
        $targets = [];

        $event = Event::find($expression->event_id);

        if ($event === null) {
            return [];
        }

        $targets[] = $event;

        if ($expression->event_session_id !== null) {
            $session = EventSession::find($expression->event_session_id);

            if ($session !== null) {
                $targets[] = $session;
            }

            return $targets;
        }

        if ($expression->event_occurrence_id !== null) {
            $occurrence = EventOccurrence::find($expression->event_occurrence_id);

            if ($occurrence !== null) {
                $targets[] = $occurrence;
            }
        }

        return $targets;
    }
}
