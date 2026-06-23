<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class Confirmed extends RegistrationStatus
{
    protected static string $name = 'confirmed';

    public static function name(): string
    {
        return 'confirmed';
    }

    public function label(): string
    {
        return 'Confirmed';
    }
}
