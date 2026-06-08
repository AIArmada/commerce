<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EventSubmissionCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Event $event,
        public readonly EventSubmission $submission,
        public readonly array $context = [],
    ) {}
}
