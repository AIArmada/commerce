<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Draft extends OccurrenceStatus
{
    protected static string $name = 'draft';

    public static function name(): string
    {
        return 'draft';
    }

    public function label(): string
    {
        return 'Draft';
    }
}
