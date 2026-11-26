<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

final readonly class SettlementPayoutRow
{
    public function __construct(
        public string $reference,
        public string $payeeCode,
        public int $totalMinor,
        public string $currency,
        public string $status,
        public int $conversionCount,
        public ?string $createdAt,
    ) {}
}
