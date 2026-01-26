<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Sent extends RecoveryAttemptStatus
{
    public static string $name = 'sent';

    public function label(): string
    {
        return 'Sent';
    }

    public function isSent(): bool
    {
        return true;
    }
}
