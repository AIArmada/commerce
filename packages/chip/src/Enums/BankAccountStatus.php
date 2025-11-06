<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

enum BankAccountStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Verification',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
        };
    }

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }
}
