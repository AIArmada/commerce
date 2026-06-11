<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Postponed = 'postponed';
    case Delayed = 'delayed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Postponed => 'Postponed',
            self::Delayed => 'Delayed',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Postponed => 'warning',
            self::Delayed => 'warning',
            self::Cancelled => 'danger',
            self::Archived => 'gray',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Active;
    }

    public function isPubliclyVisible(): bool
    {
        return match ($this) {
            self::Active,
            self::Postponed,
            self::Delayed,
            self::Cancelled,
            self::Archived => true,
            self::Draft => false,
        };
    }

    public function isEngageable(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this === self::Cancelled || $this === self::Archived;
    }

    public function isRecoverable(): bool
    {
        return $this === self::Postponed || $this === self::Delayed;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::Draft, self::Active],
            [self::Draft, self::Archived],
            [self::Active, self::Postponed],
            [self::Active, self::Delayed],
            [self::Active, self::Cancelled],
            [self::Active, self::Archived],
            [self::Postponed, self::Active],
            [self::Postponed, self::Cancelled],
            [self::Delayed, self::Active],
            [self::Delayed, self::Postponed],
            [self::Delayed, self::Cancelled] => true,
            default => false,
        };
    }
}
