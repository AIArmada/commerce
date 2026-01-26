<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Queued extends RecoveryAttemptStatus
{
    public static string $name = 'queued';

    public function label(): string
    {
        return 'Queued';
    }
}
