<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

use AIArmada\Affiliates\Enums\CommissionType;

final readonly class CommissionRuleQuote
{
    public function __construct(
        public string $id,
        public CommissionType $commissionType,
        public int $value,
    ) {}
}
