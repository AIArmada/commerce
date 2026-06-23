<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Postponed extends EventStatus
{
    protected static string $name = 'postponed';

    public static function name(): string
    {
        return 'postponed';
    }

    public function label(): string
    {
        return 'Postponed';
    }
}
