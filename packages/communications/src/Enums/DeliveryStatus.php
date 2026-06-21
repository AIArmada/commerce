<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Suppressed = 'suppressed';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Sending = 'sending';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Received = 'received';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Read = 'read';
    case Clicked = 'clicked';
    case Replied = 'replied';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Suppressed => 'Suppressed',
            self::Scheduled => 'Scheduled',
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Accepted => 'Accepted',
            self::Sent => 'Sent',
            self::Received => 'Received',
            self::Delivered => 'Delivered',
            self::Opened => 'Opened',
            self::Read => 'Read',
            self::Clicked => 'Clicked',
            self::Replied => 'Replied',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Unsubscribed => 'Unsubscribed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
