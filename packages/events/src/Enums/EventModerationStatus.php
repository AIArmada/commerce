<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum EventModerationStatus: string
{
    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::ChangesRequested => 'Changes Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::ChangesRequested => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }
}
