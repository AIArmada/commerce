<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Cancelled extends OccurrenceStatus
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
