<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

use AIArmada\Affiliates\Enums\CommissionType;

interface CommissionPricing
{
    public function calculateBase(CommissionType $type, int $rate, int $subtotalMinor): int;

    public function clamp(int $amountMinor): int;
}
