<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Services\EventMetadataSyncService;

final class EventAttributeObserver
{
    public function __construct(
        private readonly EventMetadataSyncService $sync,
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventAttribute $attribute): void
    {
        if (! config('events.sync.attributes_to_metadata')) {
            return;
        }

        $targets = $this->resolveTargets($attribute);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->sync->syncAttribute($target);
        }

        if (config('events.sync.build_search_documents') && $this->indexer !== null) {
            foreach ($targets as $target) {
                $this->indexer->index($target);
            }
        }
    }

    public function deleted(EventAttribute $attribute): void
    {
        if (! config('events.sync.attributes_to_metadata')) {
            return;
        }

        $targets = $this->resolveTargets($attribute);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->sync->syncAttribute($target);
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
    private function resolveTargets(EventAttribute $attribute): array
    {
        $targets = [];

        $event = Event::find($attribute->event_id);

        if ($event === null) {
            return [];
        }

        $targets[] = $event;

        if ($attribute->event_session_id !== null) {
            $session = EventSession::find($attribute->event_session_id);

            if ($session !== null) {
                $targets[] = $session;
            }

            return $targets;
        }

        if ($attribute->event_occurrence_id !== null) {
            $occurrence = EventOccurrence::find($attribute->event_occurrence_id);

            if ($occurrence !== null) {
                $targets[] = $occurrence;
            }
        }

        return $targets;
    }
}
