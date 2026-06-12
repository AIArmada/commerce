<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum EventModerationStatus: string
{
    use HasLabelOptions;

    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes Requested',
            self::Rejected => 'Rejected',
        };
    }
}
