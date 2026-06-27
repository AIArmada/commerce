<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum ReminderStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Expired = 'expired';
}
