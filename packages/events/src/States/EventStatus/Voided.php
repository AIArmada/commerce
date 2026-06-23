<?php

declare(strict_types=1);

namespace AIArmada\Events\States\EventStatus;

final class Voided extends EventStatus
{
    protected static string $name = 'voided';

    public static function name(): string
    {
        return 'voided';
    }

    public function label(): string
    {
        return 'Voided';
    }
}
