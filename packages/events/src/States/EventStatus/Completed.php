<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Completed extends EventStatus
{
    protected static string $name = 'completed';

    public static function name(): string
    {
        return 'completed';
    }

    public function label(): string
    {
        return 'Completed';
    }
}
