<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class NoShow extends RegistrationStatus
{
    protected static string $name = 'no_show';

    public static function name(): string
    {
        return 'no_show';
    }

    public function label(): string
    {
        return 'No Show';
    }
}
