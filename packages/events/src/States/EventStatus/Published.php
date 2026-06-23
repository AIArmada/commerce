<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Published extends EventStatus
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
