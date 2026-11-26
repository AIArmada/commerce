<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class PayoutExportRow
{
    public function __construct(
        public string $affiliateCode,
        public ?string $externalReference,
        public int $commissionMinor,
        public string $currency,
        public string $status,
        public ?string $createdAt,
    ) {}
}
