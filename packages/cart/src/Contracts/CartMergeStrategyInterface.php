<?php

declare(strict_types=1);

namespace AIArmada\Cart\Contracts;

interface CartMergeStrategyInterface
{
    public function resolveConflict(int $userQuantity, int $guestQuantity): int;
}
