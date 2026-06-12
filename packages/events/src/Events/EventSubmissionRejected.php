<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSubmission;

final class EventSubmissionRejected
{
    public function __construct(
        public EventSubmission $submission,
        public string $reason,
    ) {}
}
