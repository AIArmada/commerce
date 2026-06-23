<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class Rejected extends RegistrationStatus
{
    protected static string $name = 'rejected';

    public static function name(): string
    {
        return 'rejected';
    }

    public function label(): string
    {
        return 'Rejected';
    }
}
