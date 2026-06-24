<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Enums;

enum ModerationActionType: string
{
    case Warn = 'warn';
    case Mute = 'mute';
    case Suspend = 'suspend';
    case Ban = 'ban';
    case LiftBlock = 'lift_block';
    case Approve = 'approve';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Warn => 'Warning',
            self::Mute => 'Mute',
            self::Suspend => 'Suspend',
            self::Ban => 'Ban',
            self::LiftBlock => 'Lift Block',
            self::Approve => 'Approve',
            self::Reject => 'Reject',
        };
    }
}
