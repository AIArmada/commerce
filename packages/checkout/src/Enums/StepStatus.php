<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function isComplete(): bool
    {
        return match ($this) {
            self::Completed, self::Skipped => true,
            default => false,
        };
    }

    public function needsProcessing(): bool
    {
        return $this === self::Pending;
    }
}
