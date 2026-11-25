<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

use Carbon\CarbonImmutable;

interface PerformanceBonusCalculation
{
    /** @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}> */
    public function calculatePerformanceBonuses(
        string $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal = false,
    ): array;
}
