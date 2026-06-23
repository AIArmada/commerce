<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

final class Approved extends EventModerationStatus
{
    protected static string $name = 'approved';

    public static function name(): string
    {
        return 'approved';
    }

    public function label(): string
    {
        return 'Approved';
    }
}
