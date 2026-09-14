<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum TrackingInteractionType: string
{
    case Open = 'open';
    case Click = 'click';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Click => 'Click',
        };
    }
}
