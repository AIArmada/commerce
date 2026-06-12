<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Contracts;

use AIArmada\Engagement\Models\Reminder;
use DateTimeInterface;

interface ReminderManager
{
    public function setReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): Reminder;

    public function cancelReminder(mixed $recipient, mixed $subject, string $reminderType, array $options = []): void;

    /** @return iterable<Reminder> */
    public function dueReminders(?DateTimeInterface $at = null): iterable;

    public function markSent(Reminder $reminder): void;

    public function markFailed(Reminder $reminder, string $reason): void;
}
