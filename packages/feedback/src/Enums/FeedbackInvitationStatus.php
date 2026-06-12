<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Enums;

use AIArmada\Feedback\Enums\Concerns\HasLabelOptions;

enum FeedbackInvitationStatus: string
{
    use HasLabelOptions;

    case Pending = 'pending';
    case Sent = 'sent';
    case Opened = 'opened';
    case Started = 'started';
    case Submitted = 'submitted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Opened => 'Opened',
            self::Started => 'Started',
            self::Submitted => 'Submitted',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
