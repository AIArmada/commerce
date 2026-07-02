<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Enums;

enum PassStatus: string
{
    case Pending = 'pending';
    case Issued = 'issued';
    case Activated = 'activated';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Revoked = 'revoked';
    case Voided = 'voided';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return in_array($this, [self::Issued, self::Activated], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Used, self::Revoked, self::Voided, self::Expired], true);
    }
}
