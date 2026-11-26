<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class ConversionBeneficiary
{
    public function __construct(
        public AffiliateLifecycleReference $reference,
        public string $code,
        public string $currency,
        public bool $commercial,
        public string $commissionType,
        public int $commissionRate,
    ) {}
}
