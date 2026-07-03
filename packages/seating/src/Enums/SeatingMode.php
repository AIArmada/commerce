<?php

declare(strict_types=1);

namespace AIArmada\Seating\Enums;

enum SeatingMode: string
{
    case None = 'none';
    case GeneralAdmission = 'general_admission';
    case Assigned = 'assigned';
    case Hybrid = 'hybrid';

    public function requiresAllocation(): bool
    {
        return $this !== self::None;
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
