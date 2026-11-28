<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

interface RewardBudgetReadApi
{
    /** @return array{funded: int, reserved: int, net_spent: int, available: int} */
    public function balances(string $programId, string $currency): array;
}
