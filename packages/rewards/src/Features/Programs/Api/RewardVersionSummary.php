<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

final readonly class RewardVersionSummary
{
    public function __construct(
        public int $version,
        public string $effectiveFrom,
        public string $materialTermsHash,
    ) {}
}
