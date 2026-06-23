<?php

declare(strict_types=1);

namespace AIArmada\Events\Jobs;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Services\EventSearchDocumentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class BuildEventSearchDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Event | EventOccurrence | EventSession $target,
    ) {}

    public function uniqueId(): string
    {
        return match (true) {
            $this->target instanceof Event => 'build_event_search_doc_event_' . $this->target->id,
            $this->target instanceof EventOccurrence => 'build_event_search_doc_occurrence_' . $this->target->id,
            $this->target instanceof EventSession => 'build_event_search_doc_session_' . $this->target->id,
        };
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(EventSearchDocumentBuilder $builder): void
    {
        if (! config('events.sync.build_search_documents')) {
            return;
        }

        $builder->index($this->target);
    }
}
