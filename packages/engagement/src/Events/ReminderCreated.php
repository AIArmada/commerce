<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Features\Reminders\Models\Reminder;

final class ReminderCreated
{
    public function __construct(public Reminder $reminder) {}
}
