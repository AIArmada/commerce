<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface ScheduledPayoutSweep
{
    /** @return array{processed: int, skipped: int, errors: int} */
    public function sweep(bool $dryRun = true, ?string $affiliateId = null, int $minimum = 0): array;
}
