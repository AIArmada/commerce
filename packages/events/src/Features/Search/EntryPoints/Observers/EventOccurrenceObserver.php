<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\EntryPoints\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use Illuminate\Database\Eloquent\Model;

final class EventOccurrenceObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(Model $occurrence): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->index($occurrence);
    }

    public function deleted(Model $occurrence): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->remove($occurrence);
    }
}
