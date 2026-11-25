<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use Carbon\CarbonImmutable;

interface PerformanceBonusCalculation
{
    /** @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}> */
    public function calculatePerformanceBonuses(
        CommissionRuleType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $includeGlobal = false,
    ): array;
}
