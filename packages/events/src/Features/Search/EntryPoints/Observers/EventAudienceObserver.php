<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\EntryPoints\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

final class EventAudienceObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(Model $audience): void
    {
        $this->reindex($audience);
    }

    public function deleted(Model $audience): void
    {
        $this->reindex($audience);
    }

    private function reindex(Model $audience): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        foreach ($this->resolveTargets($audience) as $target) {
            $this->indexer->index($target);
        }
    }

    /**
     * @return list<Model>
     */
    private function resolveTargets(Model $audience): array
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->find($audience->getAttribute('event_id'));

        if (! $event instanceof Model) {
            return [];
        }

        $targets = [$event];

        $sessionId = $audience->getAttribute('event_session_id');

        if ($sessionId !== null) {
            $sessionClass = ModelResolver::sessionClass();
            $session = $sessionClass::query()->find($sessionId);

            if ($session instanceof Model) {
                $targets[] = $session;
            }

            return $targets;
        }

        $occurrenceId = $audience->getAttribute('event_occurrence_id');

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
