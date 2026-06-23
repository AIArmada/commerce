<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Draft extends EventStatus
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
