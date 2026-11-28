<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

use Carbon\CarbonImmutable;

final readonly class RewardCouponTerms
{
    /** @param array<string, mixed>|null $fulfillmentConfig */
    public function __construct(
        public string $benefitKey,
        public string $title,
        public ?array $fulfillmentConfig,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
