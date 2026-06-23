<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Archived extends OccurrenceStatus
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
