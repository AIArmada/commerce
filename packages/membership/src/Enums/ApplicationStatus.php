<?php

declare(strict_types=1);

namespace AIArmada\Membership\Enums;

/**
 * @property array{id: string, name: string, label: string, is_terminal: bool} $toArray
 */
enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Pending => false,
            self::Approved, self::Rejected, self::Cancelled => true,
        };
    }
}
