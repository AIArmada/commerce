<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\EntryPoints\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class EventClassificationObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(Model $classification): void
    {
        $this->reindex($classification);
    }

    public function deleted(Model $classification): void
    {
        $this->reindex($classification);
    }

    private function reindex(Model $classification): void
    {
        if (
            ! config('events.sync.build_search_documents')
            || ! config('events.sync.classifications_to_facets')
            || $this->indexer === null
        ) {
            return;
        }

        foreach ($this->resolveTargets($classification) as $target) {
            $this->indexer->index($target);
        }
    }

    /**
     * @return list<Model>
     */
    private function resolveTargets(Model $classification): array
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->find($classification->getAttribute('event_id'));

        if (! $event instanceof Model) {
            return [];
        }

        $targets = [$event];

        $sessionId = $classification->getAttribute('event_session_id');

        if ($sessionId !== null) {
            $sessionClass = ModelResolver::sessionClass();
            $session = $sessionClass::query()->find($sessionId);

            if ($session instanceof Model) {
                $targets[] = $session;
            }

            return $targets;
        }

        $occurrenceId = $classification->getAttribute('event_occurrence_id');

        if ($occurrenceId !== null) {
            $occurrenceClass = ModelResolver::occurrenceClass();
            $occurrence = $occurrenceClass::query()->find($occurrenceId);

            if ($occurrence instanceof Model) {
                $targets[] = $occurrence;
            }
        }

        return $targets;
    }
}
