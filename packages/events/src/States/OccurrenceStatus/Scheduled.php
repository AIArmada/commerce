<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Scheduled extends OccurrenceStatus
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
