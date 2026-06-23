<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class Interested extends RegistrationStatus
{
    protected static string $name = 'interested';

    public static function name(): string
    {
        return 'interested';
    }

    public function label(): string
    {
        return 'Interested';
    }
}
