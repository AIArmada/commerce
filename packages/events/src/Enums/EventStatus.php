<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Delayed = 'delayed';
    case Postponed = 'postponed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Archived = 'archived';
    case Voided = 'voided';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Delayed => 'Delayed',
            self::Postponed => 'Postponed',
            self::Rescheduled => 'Rescheduled',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
            self::Voided => 'Voided',
            self::Expired => 'Expired',
        };
    }
}
