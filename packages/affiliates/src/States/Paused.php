<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\States;

final class Paused extends AffiliateStatus
{
    public static string $name = 'paused';

    public function label(): string
    {
        return 'Paused';
    }

    public function description(): string
    {
        return 'Affiliate is temporarily inactive';
    }

    public function isPaused(): bool
    {
        return true;
    }
}
