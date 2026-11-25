<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\States\RegistrationStatus;

final class Pending extends RegistrationStatus
{
    protected static string $name = 'pending';

    public static function name(): string
    {
        return 'pending';
    }

    public function label(): string
    {
        return 'Pending';
    }
}
