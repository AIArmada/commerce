<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

final readonly class PayoutAccountingRecord
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $ownerType,
        public ?string $ownerId,
        public ?string $operationId,
        public int $conversionCount,
    ) {}
}
