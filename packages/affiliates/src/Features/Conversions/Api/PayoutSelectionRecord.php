<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class PayoutSelectionRecord
{
    public function __construct(
        public string $affiliateId,
        public ?string $ownerType,
        public ?string $ownerId,
        public string $currency,
        public int $totalMinor,
        public int $count,
        public bool $onlyRewardAwards,
    ) {}
}
