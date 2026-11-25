<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\EntryPoints\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use Illuminate\Database\Eloquent\Model;

final class EventSessionObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(Model $session): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->index($session);
    }

    public function deleted(Model $session): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->remove($session);
    }
}
