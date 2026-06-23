<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\EventOccurrence;

final class EventOccurrenceObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventOccurrence $occurrence): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->index($occurrence);
    }

    public function deleted(EventOccurrence $occurrence): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->remove($occurrence);
    }
}
