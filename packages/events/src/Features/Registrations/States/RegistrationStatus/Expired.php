<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\States\RegistrationStatus;

final class Expired extends RegistrationStatus
{
    protected static string $name = 'expired';

    public static function name(): string
    {
        return 'expired';
    }

    public function label(): string
    {
        return 'Expired';
    }
}
