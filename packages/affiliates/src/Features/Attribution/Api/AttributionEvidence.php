<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use Carbon\CarbonImmutable;

final readonly class AttributionEvidence
{
    public function __construct(
        public string $affiliateId,
        public ?CarbonImmutable $firstSeenAt,
    ) {}
}
