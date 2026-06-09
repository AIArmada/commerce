<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Support\Policy\LifecyclePolicy;

enum OccurrenceStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Live => 'Live',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Live => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }

    public function acceptsRegistrations(): bool
    {
        return LifecyclePolicy::occurrenceAcceptsRegistrations($this);
    }
}
