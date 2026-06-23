<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Expired extends EventStatus
{
    protected static string $name = 'expired';

    public static function name(): string
    {
        return 'expired';
    }

    public function label(): string
    {
        return 'Expired';
    }
}
