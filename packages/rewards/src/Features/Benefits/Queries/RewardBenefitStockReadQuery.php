<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Queries;

use AIArmada\Rewards\Features\Benefits\Api\RewardBenefitStockReadApi;
use AIArmada\Rewards\Features\Benefits\Persistence\RewardBenefitGuard;
use AIArmada\Rewards\Features\Benefits\Persistence\RewardBenefitStockLedger;

final readonly class RewardBenefitStockReadQuery implements RewardBenefitStockReadApi
{
    public function balances(string $itemId): array
    {
        return RewardBenefitStockLedger::balances(RewardBenefitGuard::findItemOrFail($itemId));
    }
}
