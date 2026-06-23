<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

final class Pending extends EventModerationStatus
{
    protected static string $name = 'pending';

    public static function name(): string
    {
        return 'pending';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
