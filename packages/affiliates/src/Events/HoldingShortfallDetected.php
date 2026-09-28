<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class HoldingShortfallDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AffiliateConversion $conversion,
        public readonly string $operation,
        public readonly int $requestedMinor,
        public readonly int $appliedMinor
    ) {}
}
