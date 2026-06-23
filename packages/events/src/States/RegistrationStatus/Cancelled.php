<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class Cancelled extends RegistrationStatus
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
