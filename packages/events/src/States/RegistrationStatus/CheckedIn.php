<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class CheckedIn extends RegistrationStatus
{
    protected static string $name = 'checked_in';

    public static function name(): string
    {
        return 'checked_in';
    }

    public function label(): string
    {
        return 'Checked In';
    }
}
