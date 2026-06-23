<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Live extends OccurrenceStatus
{
    protected static string $name = 'live';

    public static function name(): string
    {
        return 'live';
    }

    public function label(): string
    {
        return 'Live';
    }
}
