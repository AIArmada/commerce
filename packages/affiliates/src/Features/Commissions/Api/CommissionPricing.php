<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionPricing
{
    public function calculateBase(string $type, int $rate, int $subtotalMinor): int;

    public function clamp(int $amountMinor): int;
}
