<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
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

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::Draft, self::Active],
            [self::Draft, self::Archived],
            [self::Active, self::Cancelled],
            [self::Active, self::Archived] => true,
            default => false,
        };
    }
}
