<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Api;

interface RewardBenefitStockReadApi
{
    /** @return array{capacity: int, reserved: int, consumed: int, available: int} */
    public function balances(string $itemId): array;
}
