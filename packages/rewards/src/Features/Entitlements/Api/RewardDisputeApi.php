<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

use Carbon\CarbonImmutable;

interface RewardDisputeApi
{
    /** @param array<string, mixed> $amountSnapshot */
    public function recordConsumedPointsDispute(string $awardId, array $amountSnapshot, string $actorType, ?string $actorId, string $actorRole, string $reason, CarbonImmutable $decided): string;
}
