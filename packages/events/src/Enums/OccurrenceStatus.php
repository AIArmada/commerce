<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum OccurrenceStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Live = 'live';
    case Delayed = 'delayed';
    case Postponed = 'postponed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Live => 'Live',
            self::Delayed => 'Delayed',
            self::Postponed => 'Postponed',
            self::Rescheduled => 'Rescheduled',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }
}
