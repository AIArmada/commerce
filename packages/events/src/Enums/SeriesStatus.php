<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum SeriesStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Archived => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Archived;
    }

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::Active, self::Inactive],
            [self::Active, self::Archived],
            [self::Inactive, self::Active],
            [self::Inactive, self::Archived],
            [self::Archived, self::Active] => true,
            default => false,
        };
    }
}
