<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum CommunicationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Processing = 'processing';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::PartiallyCompleted => 'Partially Completed',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
