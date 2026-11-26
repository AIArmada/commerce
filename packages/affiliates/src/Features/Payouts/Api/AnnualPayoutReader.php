<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface AnnualPayoutReader
{
    public function assertSharedConnection(int $connectionIdentity): void;

    /** @return list<string> */
    public function eligibleAffiliateIds(int $year, string $currency, int $minimumMinor): array;

    public function total(string $affiliateId, int $year, string $currency): int;

    /** @return list<array{reference: string, currency: string, amount_minor: int}> */
    public function excluded(string $affiliateId, int $year, string $currency): array;
}
