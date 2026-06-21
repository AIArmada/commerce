<?php

declare(strict_types=1);

namespace AIArmada\Communications\Enums;

enum ThreadStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }
}
