<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

final readonly class PayoutEventRecord
{
    public function __construct(
        public string $id,
        public ?string $fromStatus,
        public string $toStatus,
        public ?string $notes,
        public ?string $createdAt,
    ) {}
}
