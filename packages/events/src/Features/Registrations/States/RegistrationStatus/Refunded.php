<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\States\RegistrationStatus;

final class Refunded extends RegistrationStatus
{
    protected static string $name = 'refunded';

    public static function name(): string
    {
        return 'refunded';
    }

    public function label(): string
    {
        return 'Refunded';
    }
}
