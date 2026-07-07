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
    case ChangesRequested = 'changes_requested';
    case Cancelled = 'cancelled';
    case RevertedToDraft = 'reverted_to_draft';
    case Reconsidered = 'reconsidered';
    case Remoderated = 'remoderated';

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
            self::ChangesRequested => 'Changes Requested',
            self::Cancelled => 'Cancelled',
            self::RevertedToDraft => 'Reverted to Draft',
            self::Reconsidered => 'Reconsidered',
            self::Remoderated => 'Remoderated',
        };
    }
}
