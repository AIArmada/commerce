<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Delivered extends RecoveryAttemptStatus
{
    public static string $name = 'delivered';

    public function label(): string
    {
        return 'Delivered';
    }

    public function isSent(): bool
    {
        return true;
    }
}
