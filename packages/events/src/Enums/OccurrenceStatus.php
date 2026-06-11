<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;
use AIArmada\Events\Support\Policy\LifecyclePolicy;

enum OccurrenceStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case Delayed = 'delayed';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Postponed => 'Postponed',
            self::Delayed => 'Delayed',
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
            self::Postponed => 'warning',
            self::Delayed => 'warning',
            self::Live => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    public function isRecoverable(): bool
    {
        return $this === self::Postponed || $this === self::Delayed;
    }

    public function acceptsRegistrations(): bool
    {
        return LifecyclePolicy::occurrenceAcceptsRegistrations($this);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::Draft, self::Scheduled],
            [self::Draft, self::Cancelled],
            [self::Scheduled, self::Postponed],
            [self::Scheduled, self::Delayed],
            [self::Scheduled, self::Live],
            [self::Scheduled, self::Completed],
            [self::Scheduled, self::Cancelled],
            [self::Postponed, self::Scheduled],
            [self::Postponed, self::Cancelled],
            [self::Delayed, self::Scheduled],
            [self::Delayed, self::Live],
            [self::Delayed, self::Cancelled],
            [self::Live, self::Completed],
            [self::Live, self::Cancelled],
            [self::Completed, self::Cancelled] => true,
            default => false,
        };
    }
}
