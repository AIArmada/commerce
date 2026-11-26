<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionRuleCalculation
{
    /** @param array<string, mixed> $context */
    public function calculate(string $affiliateId, int $orderAmountMinor, array $context = []): CommissionCalculationResult;
}
