<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Delayed extends EventStatus
{
    protected static string $name = 'delayed';

    public static function name(): string
    {
        return 'delayed';
    }

    public function label(): string
    {
        return 'Delayed';
    }
}
