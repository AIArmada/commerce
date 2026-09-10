<?php

declare(strict_types=1);

namespace AIArmada\Seating\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum SeatingMode: string
{
    use HasLabelOptions;

    case None = 'none';
    case GeneralAdmission = 'general_admission';
    case Assigned = 'assigned';
    case Hybrid = 'hybrid';

    public function requiresAllocation(): bool
    {
        return $this !== self::None;
    }

    public function requiresSeatAllocation(): bool
    {
        return in_array($this, [self::Assigned, self::Hybrid], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::GeneralAdmission => 'General Admission',
            self::Assigned => 'Assigned',
            self::Hybrid => 'Hybrid',
        };
    }
}
