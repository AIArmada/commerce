<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

final class Rejected extends EventModerationStatus
{
    protected static string $name = 'rejected';

    public static function name(): string
    {
        return 'rejected';
    }

    public function label(): string
    {
        return 'Rejected';
    }
}
