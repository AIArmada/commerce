<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

final readonly class CommissionRuleQuote
{
    public function __construct(
        public string $id,
        public string $commissionType,
        public int $value,
    ) {}
}
