<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventRegistrationQuestionArchived
{
    public function __construct(
        public string $questionId,
        public string $eventId,
        public ?string $occurrenceId = null,
        public ?string $sessionId = null,
    ) {}
}
