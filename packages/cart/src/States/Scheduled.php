<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Scheduled extends RecoveryAttemptStatus
{
    public static string $name = 'scheduled';

    public function label(): string
    {
        return 'Scheduled';
    }

    public function isScheduled(): bool
    {
        return true;
    }
}
