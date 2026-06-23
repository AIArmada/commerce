<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Delayed extends OccurrenceStatus
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
