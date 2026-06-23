<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Scheduled extends EventStatus
{
    protected static string $name = 'scheduled';

    public static function name(): string
    {
        return 'scheduled';
    }

    public function label(): string
    {
        return 'Scheduled';
    }
}
