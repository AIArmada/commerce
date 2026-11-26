<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

use Carbon\CarbonImmutable;

interface RankQualificationApi
{
    public function evaluate(string $affiliateId): ?QualifiedRank;

    public function processAllRankUpgrades(bool $dryRun = false): int;

    public function processRankChange(string $affiliateId): void;

    /** @param iterable<string> $affiliateIds */
    public function processBatch(iterable $affiliateIds): void;

    public function assignRank(string $affiliateId, ?string $rankId): void;

    /** @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int} */
    public function calculateMetrics(string $affiliateId, ?CarbonImmutable $from = null, ?string $currency = null): array;
}
