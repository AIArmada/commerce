<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

final class ChangesRequested extends EventModerationStatus
{
    protected static string $name = 'changes_requested';

    public static function name(): string
    {
        return 'changes_requested';
    }

    public function label(): string
    {
        return 'Changes Requested';
    }
}
