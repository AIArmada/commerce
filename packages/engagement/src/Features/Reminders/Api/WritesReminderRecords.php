<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Api;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\Remindable;

interface WritesReminderRecords
{
    /** @return array<string, mixed> */
    public function recordReminder(CanInteract $recipient, Remindable $subject, string $reminderType, array $options = []): array;
}
