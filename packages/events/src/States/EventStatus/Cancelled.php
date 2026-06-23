<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Cancelled extends EventStatus
{
    protected static string $name = 'cancelled';

    public static function name(): string
    {
        return 'cancelled';
    }

    public function label(): string
    {
        return 'Cancelled';
    }
}
