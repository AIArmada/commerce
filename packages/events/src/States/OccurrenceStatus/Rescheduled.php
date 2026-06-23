<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Rescheduled extends OccurrenceStatus
{
    protected static string $name = 'rescheduled';

    public static function name(): string
    {
        return 'rescheduled';
    }

    public function label(): string
    {
        return 'Rescheduled';
    }
}
