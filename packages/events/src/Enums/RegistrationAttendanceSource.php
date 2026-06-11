<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\Events\Enums\Concerns\HasLabelOptions;

enum RegistrationAttendanceSource: string
{
    use HasLabelOptions;

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
