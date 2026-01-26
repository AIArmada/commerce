<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Cancelled extends RecoveryAttemptStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'Cancelled';
    }
}
