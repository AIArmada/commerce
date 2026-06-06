<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum RegistrationAttendanceSource: string
{
    case Registration = 'registration';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::WalkIn => 'Walk-in',
        };
    }
}
