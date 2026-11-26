<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class BalanceSnapshot
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $currency,
        public int $holdingMinor,
        public int $availableMinor,
        public int $lifetimeEarningsMinor,
        public int $minimumPayoutMinor,
        public int $payoutSequence,
    ) {}

    public function canRequestPayout(): bool
    {
        return $this->availableMinor > 0 && $this->availableMinor >= $this->minimumPayoutMinor;
    }
}
