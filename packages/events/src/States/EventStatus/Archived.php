<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Archived extends EventStatus
{
    protected static string $name = 'archived';

    public static function name(): string
    {
        return 'archived';
    }

    public function label(): string
    {
        return 'Archived';
    }
}
