<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

interface RewardDecisionHistoryApi
{
    /** @return list<array{transition: string, reason: string, decided_by: string, decided_at: string}> */
    public function awardHistory(string $awardId): array;

    /** @return list<array{transition: string, reason: string, decided_by: string, decided_at: string}> */
    public function appealHistory(string $appealId): array;
}
