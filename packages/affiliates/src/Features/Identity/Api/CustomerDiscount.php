<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class CustomerDiscount
{
    public function __construct(
        public string $affiliateId,
        public string $affiliateCode,
        public string $affiliateName,
        public string $type,
        public int $value,
    ) {}
}
