<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

use AIArmada\CommerceSupport\Traits\HasLabelOptions;

enum ScheduleKind: string
{
    use HasLabelOptions;

    case Single = 'single';
    case MultiDay = 'multi_day';
    case CustomChain = 'custom_chain';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::MultiDay => 'Multi-day',
            self::CustomChain => 'Custom Chain',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Single => 'primary',
            self::MultiDay => 'success',
            self::CustomChain => 'warning',
        };
    }
}
