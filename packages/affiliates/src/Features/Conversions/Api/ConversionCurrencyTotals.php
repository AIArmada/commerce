<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class ConversionCurrencyTotals
{
    public function __construct(
        public string $currency,
        public int $count,
        public int $revenueMinor,
        public int $commissionMinor,
    ) {}
}
