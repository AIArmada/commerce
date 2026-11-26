<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class VoucherConversionRecord
{
    public function __construct(
        public string $affiliateId,
        public string $affiliateCode,
        public ?string $affiliateName,
        public ?string $attributionId,
        public ?string $ownerType,
        public ?string $ownerId,
        public int $connectionIdentity,
    ) {}
}
