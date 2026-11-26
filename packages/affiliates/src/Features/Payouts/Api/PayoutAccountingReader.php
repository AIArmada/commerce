<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutAccountingReader
{
    public function hasAffiliateHistory(string $affiliateId, int $connectionIdentity): bool;

    public function isOpen(string $payoutId, int $connectionIdentity): bool;

    public function assertConversionLink(
        string $payoutId,
        string $conversionId,
        ?string $existingConversionId,
        string $affiliateId,
        string $currency,
        int $connectionIdentity,
    ): void;

    public function locked(string $payoutId, int $connectionIdentity): PayoutAccountingRecord;
}
