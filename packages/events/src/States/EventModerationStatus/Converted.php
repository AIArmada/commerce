<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventModerationStatus;

final class Converted extends EventModerationStatus
{
    protected static string $name = 'converted';

    public static function name(): string
    {
        return 'converted';
    }

    public function label(): string
    {
        return 'Converted';
    }
}
