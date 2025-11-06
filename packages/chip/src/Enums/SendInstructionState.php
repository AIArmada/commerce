<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * Official CHIP Send Instruction State Values
 *
 * Source: https://docs.chip-in.asia/chip-send/api-reference/send-instructions
 * All values are directly from official CHIP API documentation
 */
enum SendInstructionState: string
{
    case RECEIVED = 'received';
    case ENQUIRING = 'enquiring';
    case EXECUTING = 'executing';
    case REVIEWING = 'reviewing';
    case ACCEPTED = 'accepted';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::ENQUIRING => 'Enquiring',
            self::EXECUTING => 'Executing',
            self::REVIEWING => 'Under Review',
            self::ACCEPTED => 'Accepted',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
            self::DELETED => 'Deleted',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isPending(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::ENQUIRING,
            self::EXECUTING,
            self::REVIEWING,
            self::ACCEPTED,
        ]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::REJECTED, self::DELETED]);
    }

    public function canBeDeleted(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::ENQUIRING,
            self::REVIEWING,
        ]);
    }
}
