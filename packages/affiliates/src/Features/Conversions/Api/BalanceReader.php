<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface BalanceReader
{
    public function find(string $affiliateId, string $currency): ?BalanceSnapshot;

    public function lock(string $affiliateId, string $currency, int $connectionIdentity): ?BalanceSnapshot;

    /** @return list<BalanceSnapshot> */
    public function forAffiliate(string $affiliateId): array;

    public function hasRecords(string $affiliateId): bool;

    public function canRequestPayout(string $affiliateId, ?string $currency = null): bool;

    /** @return iterable<BalanceSnapshot> */
    public function payoutCandidates(int $minimum, ?string $affiliateId = null): iterable;
}
