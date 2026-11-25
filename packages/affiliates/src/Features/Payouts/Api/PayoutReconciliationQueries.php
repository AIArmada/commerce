<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutReconciliationQueries
{
    /** @return list<string> */
    public function payoutIdsNeedingReconciliation(): array;

    /** @return array<string, mixed> */
    public function generateReport(?string $startDate = null, ?string $endDate = null): array;

    /**
     * @param list<string> $payoutIds
     * @return array<string, mixed>
     */
    public function summarizePayoutIds(array $payoutIds, ?string $startDate = null, ?string $endDate = null, bool $includeGlobal = false): array;

    /** @return array<string, array<string, int|string|bool>> */
    public function auditAllAffiliateBalances(string $affiliateId): array;

    /** @return array<string, int|string|bool> */
    public function auditAffiliateBalance(string $affiliateId, string $currency): array;
}
