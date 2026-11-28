<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

use Carbon\CarbonImmutable;

interface RewardBudgetApi
{
    public function reserveObligation(string $programId, string $currency, string $awardId, int $amount, int $epoch, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void;

    public function releaseObligation(string $programId, string $currency, string $awardId, int $amount, int $epoch, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void;

    public function recoverConfirmedSpend(string $programId, string $currency, string $awardId, int $amount, string $recoveryKey, string $actorType, ?string $actorId, string $actorRole, CarbonImmutable $decided): void;
}
