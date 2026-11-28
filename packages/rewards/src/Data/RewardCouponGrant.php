<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

final readonly class RewardCouponGrant
{
    public function __construct(
        public string $grantKey,
        public string $recipientType,
        public string $recipientId,
        public int $quantity,
        public ?string $externalReference,
    ) {}
}
