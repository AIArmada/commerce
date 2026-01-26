<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Failed extends RecoveryAttemptStatus
{
    public static string $name = 'failed';

    public function label(): string
    {
        return 'Failed';
    }

    public function isFailed(): bool
    {
        return true;
    }
}
