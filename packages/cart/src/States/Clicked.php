<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Clicked extends RecoveryAttemptStatus
{
    public static string $name = 'clicked';

    public function label(): string
    {
        return 'Clicked';
    }

    public function isSent(): bool
    {
        return true;
    }

    public function isOpened(): bool
    {
        return true;
    }

    public function isClicked(): bool
    {
        return true;
    }
}
