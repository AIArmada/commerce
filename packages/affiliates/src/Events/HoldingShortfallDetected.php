<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class HoldingShortfallDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $conversionId,
        public string $affiliateId,
        public string $currency,
        public string $operation,
        public int $requestedMinor,
        public int $appliedMinor
    ) {}
}
