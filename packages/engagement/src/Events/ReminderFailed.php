<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

final readonly class ReminderFailed
{
    public function __construct(
        public string $reminderId,
        public string $recipientType,
        public string $recipientId,
        public string $subjectType,
        public string $subjectId,
        public ?string $ownerType,
        public ?string $ownerId,
        public string $reminderType,
        public string $reason,
    ) {}
}
