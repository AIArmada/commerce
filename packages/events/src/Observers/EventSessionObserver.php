<?php

declare(strict_types=1);

namespace AIArmada\Events\Observers;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Models\EventSession;

final class EventSessionObserver
{
    public function __construct(
        private readonly ?EventSearchIndexer $indexer = null,
    ) {}

    public function saved(EventSession $session): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->index($session);
    }

    public function deleted(EventSession $session): void
    {
        if (! config('events.sync.build_search_documents') || $this->indexer === null) {
            return;
        }

        $this->indexer->remove($session);
    }
}
