<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

interface RewardPayoutQueryApi
{
    public function forAward(string $awardId, bool $lock = false): ?RewardPayoutSnapshot;
}
