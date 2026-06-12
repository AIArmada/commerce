<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Reminder;

final class ReminderDue
{
    public function __construct(public Reminder $reminder) {}
}
