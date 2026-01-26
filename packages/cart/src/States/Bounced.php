<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Bounced extends RecoveryAttemptStatus
{
    public static string $name = 'bounced';

    public function label(): string
    {
        return 'Bounced';
    }

    public function isFailed(): bool
    {
        return true;
    }
}
