<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventRegistrationQuestionData
{
    /**
     * @param  array<int|string, mixed>  $options
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public string $fieldKey,
        public string $question,
        public ?string $description,
        public EventRegistrationQuestionType $type,
        public array $options,
        public bool $isRequired,
        public EventRegistrationQuestionStatus $status,
        public int $orderColumn,
        public ?array $metadata = null,
    ) {}
}
