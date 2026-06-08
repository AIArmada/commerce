<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum EventEngagementType: string
{
    case Saved = 'saved';
    case Going = 'going';
    case Interested = 'interested';

    public function label(): string
    {
        return match ($this) {
            self::Saved => 'Saved',
            self::Going => 'Going',
            self::Interested => 'Interested',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Saved => 'gray',
            self::Going => 'success',
            self::Interested => 'info',
        };
    }
}
