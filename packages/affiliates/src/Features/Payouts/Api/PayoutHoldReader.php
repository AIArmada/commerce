<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutHoldReader
{
    public function hasActive(string $affiliateId): bool;

    public function count(string $affiliateId): int;

    /** Integrity predicate for affiliate removal. */
    public function hasRecords(string $affiliateId): bool;
}
