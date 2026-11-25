<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateAccountSnapshot
{
    public function __construct(
        public string $id,
        public string $code,
        public string $status,
        public string $commissionType,
        public int $commissionRate,
        public string $currency,
    ) {}
}
