<?php

declare(strict_types=1);

namespace AIArmada\Cart\States;

final class Converted extends RecoveryAttemptStatus
{
    public static string $name = 'converted';

    public function label(): string
    {
        return 'Converted';
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

    public function isConverted(): bool
    {
        return true;
    }
}
