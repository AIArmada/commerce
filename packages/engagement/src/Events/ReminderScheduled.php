<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Features\Reminders\Models\Reminder;

final class ReminderScheduled
{
    public function __construct(public Reminder $reminder) {}
}
