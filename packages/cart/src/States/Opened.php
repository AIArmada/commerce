<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Opened extends RecoveryAttemptStatus
{
    public static string $name = 'opened';

    public function label(): string
    {
        return 'Opened';
    }

    public function isSent(): bool
    {
        return true;
    }

    public function isOpened(): bool
    {
        return true;
    }
}
