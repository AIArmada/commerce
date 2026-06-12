<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventSubmission;

final class EventSubmissionApproved
{
    public function __construct(
        public EventSubmission $submission,
    ) {}
}
