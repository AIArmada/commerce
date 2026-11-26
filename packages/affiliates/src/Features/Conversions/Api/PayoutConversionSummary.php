<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

final readonly class PayoutConversionSummary
{
    /** @param list<string> $affiliateIds */
    public function __construct(
        public array $affiliateIds,
        public int $count,
        public int $totalMinor,
        public int $approvedCount,
        public int $rewardAwardCount,
        public int $foreignOwnerCount,
    ) {}
}
