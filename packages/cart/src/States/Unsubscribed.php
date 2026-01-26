<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Unsubscribed extends RecoveryAttemptStatus
{
    public static string $name = 'unsubscribed';

    public function label(): string
    {
        return 'Unsubscribed';
    }
}
