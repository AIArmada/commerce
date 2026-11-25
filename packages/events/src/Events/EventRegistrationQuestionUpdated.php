<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventRegistrationQuestionUpdated
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public string $questionId,
        public string $eventId,
        public ?string $occurrenceId = null,
        public ?string $sessionId = null,
        public array $changes = [],
    ) {}
}
