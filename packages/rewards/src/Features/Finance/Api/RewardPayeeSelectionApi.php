<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

interface RewardPayeeSelectionApi
{
    /** @return array<string, string> */
    public function optionsForAward(string $awardId): array;

    public function methodForAward(string $awardId, string $payeeId): ?string;
}
