<?php

declare(strict_types=1);

namespace AIArmada\Events\States\OccurrenceStatus;

final class Published extends OccurrenceStatus
{
    protected static string $name = 'published';

    public static function name(): string
    {
        return 'published';
    }

    public function label(): string
    {
        return 'Published';
    }
}
