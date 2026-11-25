<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventSubmissionApproved
{
    public function __construct(
        public string $submissionId,
        public string $eventId,
    ) {}
}
