<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutEventHistory
{
    /** Integrity check for payout deletion, independent of event visibility. */
    public function hasEvents(string $payoutId): bool;

    /** @return list<PayoutEventRecord> */
    public function forPayout(string $payoutId, int $limit = 100): array;
}
