<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Queries;

use AIArmada\Rewards\Features\Finance\Api\RewardBudgetReadApi;
use AIArmada\Rewards\Features\Finance\Persistence\RewardBudgetLedger;
use AIArmada\Rewards\Features\Programs\Api\RewardProgramLockApi;

final readonly class RewardBudgetReadQuery implements RewardBudgetReadApi
{
    public function __construct(private RewardProgramLockApi $programs) {}

    public function balances(string $programId, string $currency): array
    {
        $this->programs->assertProgramInScope($programId);

        return RewardBudgetLedger::balances($programId, $currency);
    }
}
