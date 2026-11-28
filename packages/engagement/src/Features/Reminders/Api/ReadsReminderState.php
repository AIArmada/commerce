<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Reminders\Api;

interface ReadsReminderState
{
    public function remindersFor(mixed $recipient, mixed $subject): array;
}
