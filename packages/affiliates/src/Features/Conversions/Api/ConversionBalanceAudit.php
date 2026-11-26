<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class ConversionBalanceAudit
{
    /** @param array<string, int> $paidByPayout */
    public function __construct(
        public int $approvedMinor,
        public int $directPaidClawbacksMinor,
        public array $paidByPayout,
    ) {}
}
