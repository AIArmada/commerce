<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\States\RegistrationStatus;

final class Waitlisted extends RegistrationStatus
{
    protected static string $name = 'waitlisted';

    public static function name(): string
    {
        return 'waitlisted';
    }

    public function label(): string
    {
        return 'Waitlisted';
    }
}
