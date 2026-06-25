<?php

declare(strict_types=1);

namespace AIArmada\Membership\Enums;

/**
 * @property array{id: string, name: string, label: string, is_terminal: bool} $toArray
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Pending => false,
            self::Accepted, self::Revoked, self::Expired => true,
        };
    }
}
