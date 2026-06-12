<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Reminder;

final class ReminderFailed
{
    public function __construct(public Reminder $reminder, public string $reason) {}
}
